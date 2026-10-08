<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use Sabre\VObject\Component\VEvent;
use Sabre\VObject\Reader;

/**
 * Leest een gepubliceerde Outlook-agenda (ICS-link) en geeft terug op welke
 * momenten de medewerker bezet is.
 *
 * VEILIGHEID
 * - De link werkt als een sleutel: wie hem heeft, ziet wanneer iemand bezet is.
 *   Daarom staat hij nooit in een foutmelding, in de log of in de cache-sleutel.
 * - We halen alleen iets op bij Microsoft zelf (https + vaste lijst met adressen)
 *   en volgen geen doorverwijzingen. Zo kan niemand het portaal via een
 *   "agendalink" een ander adres laten opvragen.
 * - We bewaren alleen begin- en eindtijden. Onderwerpen, locaties en namen
 *   worden nergens opgeslagen (ze horen ook niet in de link te staan).
 */
class OutlookCalendar
{
    // Zelfde tijdzone als AppointmentAvailability
    public const TIMEZONE = 'Europe/Amsterdam';

    // Een agenda is hooguit een paar honderd kB. Alles daarboven vertrouwen we niet.
    private const MAX_BYTES = 5 * 1024 * 1024;

    /**
     * Bezette momenten die het tijdvak tussen $from en $to raken, oplopend gesorteerd.
     * Een afspraak die precies eindigt op $from of begint op $to telt niet mee.
     *
     * @return list<array{start: CarbonImmutable, end: CarbonImmutable}>
     */
    public function busyIntervals(string $icsUrl, DateTimeInterface $from, DateTimeInterface $to): array
    {
        $fromTime = $from->getTimestamp();
        $toTime = $to->getTimestamp();

        $result = [];

        foreach ($this->allBusyIntervals($icsUrl) as [$start, $end]) {
            if ($start < $toTime && $end > $fromTime) {
                $result[] = [
                    'start' => CarbonImmutable::createFromTimestamp($start, self::TIMEZONE),
                    'end'   => CarbonImmutable::createFromTimestamp($end, self::TIMEZONE),
                ];
            }
        }

        return $result;
    }

    /**
     * Alle bezette momenten van vandaag tot een jaar vooruit, als [begin, einde]
     * in Unix-tijd. Het resultaat wordt kort onthouden, zodat we Outlook niet
     * bij elk tijdslot opnieuw hoeven te vragen.
     *
     * @return list<array{0: int, 1: int}>
     */
    private function allBusyIntervals(string $icsUrl): array
    {
        $this->assertAllowedUrl($icsUrl);

        // De link zelf mag niet leesbaar in de cache staan, dus alleen een vingerafdruk ervan
        $cacheKey = 'outlook_calendar:' . hash('sha256', $icsUrl);

        return Cache::remember(
            $cacheKey,
            now()->addMinutes((int) config('services.outlook.cache_minutes', 5)),
            function () use ($icsUrl) {
                $from = CarbonImmutable::today(self::TIMEZONE);
                $to = $from->addDays((int) config('services.outlook.days_ahead', 365));

                return $this->parse($this->download($icsUrl), $from, $to);
            }
        );
    }

    /**
     * Alleen https en alleen de adressen van Microsoft uit config/services.php.
     */
    private function assertAllowedUrl(string $icsUrl): void
    {
        $parts = parse_url($icsUrl);
        $allowedHosts = config('services.outlook.allowed_hosts', ['outlook.office365.com', 'outlook.office.com']);

        $valid = is_array($parts)
            && strtolower($parts['scheme'] ?? '') === 'https'
            && in_array(strtolower($parts['host'] ?? ''), $allowedHosts, true)
            // Geen gebruikersnaam, wachtwoord of afwijkende poort in de link
            && ! isset($parts['user'])
            && ! isset($parts['pass'])
            && (! isset($parts['port']) || $parts['port'] === 443);

        if (! $valid) {
            // Bewust zonder de link in de melding
            throw new InvalidArgumentException('Dit is geen geldige Outlook-agendalink.');
        }
    }

    private function download(string $icsUrl): string
    {
        try {
            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->withoutRedirecting()
                ->accept('text/calendar')
                ->get($icsUrl);
        } catch (ConnectionException) {
            // De oorspronkelijke melding bevat het adres (dus de geheime link): niet doorgeven
            throw new RuntimeException('De Outlook-agenda is op dit moment niet bereikbaar.');
        }

        if (! $response->successful()) {
            throw new RuntimeException("De Outlook-agenda gaf een fout ({$response->status()}).");
        }

        $body = $response->body();

        if (strlen($body) > self::MAX_BYTES) {
            throw new RuntimeException('De Outlook-agenda is te groot om te verwerken.');
        }

        // Bijvoorbeeld een inlogpagina in plaats van een agenda
        if (! str_contains($body, 'BEGIN:VCALENDAR')) {
            throw new RuntimeException('De Outlook-link gaf geen agenda terug.');
        }

        return $body;
    }

    /**
     * Zet de agendatekst om naar bezette momenten.
     *
     * Het lezen zelf doet de bibliotheek sabre/vobject. Die regelt de lastige
     * onderdelen: herhalende afspraken (met uitzonderingen), zomer- en wintertijd
     * en de Windows-namen die Outlook voor tijdzones gebruikt.
     *
     * @return list<array{0: int, 1: int}>
     */
    private function parse(string $ics, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $timezone = new DateTimeZone(self::TIMEZONE);

        try {
            $calendar = Reader::read($ics, Reader::OPTION_FORGIVING);

            // expand() maakt van elke reeks losse afspraken binnen de periode.
            // Afspraken zonder tijdzone (zoals hele dagen) gelden als Nederlandse tijd.
            $events = $calendar->expand($from, $to, $timezone)->select('VEVENT');
        } catch (\Throwable) {
            throw new RuntimeException('De Outlook-agenda kon niet worden gelezen.');
        }

        $busyStatuses = array_map('strtoupper', config('services.outlook.busy_statuses', ['BUSY', 'OOF', 'TENTATIVE']));
        $intervals = [];

        foreach ($events as $event) {
            if (! isset($event->DTSTART)) {
                continue;
            }

            if (strtoupper((string) ($event->STATUS ?? '')) === 'CANCELLED') {
                continue;
            }

            if (! in_array($this->busyStatus($event), $busyStatuses, true)) {
                continue;
            }

            $start = $event->DTSTART->getDateTime($timezone);
            $end = $this->endOf($event, $start, $timezone);

            if ($end > $start) {
                $intervals[] = [$start->getTimestamp(), $end->getTimestamp()];
            }
        }

        sort($intervals);

        return $intervals;
    }

    /**
     * Outlook zet bij elke afspraak of je dan BUSY (bezet), TENTATIVE (voorlopig),
     * FREE (beschikbaar) of OOF (afwezig) bent. Staat dat er niet, dan kijken we
     * naar het standaardveld TRANSP: TRANSPARENT betekent "houdt geen tijd bezet".
     */
    private function busyStatus(VEvent $event): string
    {
        $status = strtoupper(trim((string) ($event->{'X-MICROSOFT-CDO-BUSYSTATUS'} ?? '')));

        if ($status !== '') {
            return $status;
        }

        return strtoupper((string) ($event->TRANSP ?? '')) === 'TRANSPARENT' ? 'FREE' : 'BUSY';
    }

    private function endOf(VEvent $event, DateTimeImmutable $start, DateTimeZone $timezone): DateTimeImmutable
    {
        if (isset($event->DTEND)) {
            return $event->DTEND->getDateTime($timezone);
        }

        if (isset($event->DURATION)) {
            return $start->add($event->DURATION->getDateInterval());
        }

        // Zonder eindtijd: een hele dag duurt één dag, een afspraak met tijd duurt niets
        return $event->DTSTART->hasTime() ? $start : $start->add(new DateInterval('P1D'));
    }
}