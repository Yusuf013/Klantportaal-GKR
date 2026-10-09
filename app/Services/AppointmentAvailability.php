<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

/**
 * Eén centrale plek voor alle regels rond beschikbaarheid van afspraken.
 *
 * Voorheen stonden deze regels verspreid (deels alleen in JavaScript).
 * Nu gebruiken de klant- en admincontroller allebei deze class, zodat
 * de regels overal hetzelfde zijn.
 *
 * Een medewerker is bezet als er in het portaal al een afspraak staat, of als
 * er in de gekoppelde Outlook-agenda iets staat (zie OutlookCalendar).
 */
class AppointmentAvailability
{
    // Alle tijden in de database zijn Nederlandse tijd
    public const TIMEZONE = 'Europe/Amsterdam';

    // Dezelfde tijdslots als in de kalender (standardSlots in JavaScript)
    public const SLOTS = [
        '09:00 - 10:00',
        '10:00 - 11:00',
        '11:00 - 12:00',
        '13:00 - 14:00',
        '14:00 - 15:00',
        '15:00 - 16:00',
    ];

    // Afspraken met deze status houden een tijdslot bezet.
    // Niet: 'Voorstel' (dat zijn nog maar opties) en 'Geannuleerd'.
    public const BLOCKING_STATUSES = [
        'In afwachting',
        'Bevestigd door klant',
        'Alternatief gekozen',
        'Bevestigd',
    ];

    // Zoveel minuten moet er voor en na een afspraak vrij blijven, per soort afspraak
    // (afgesproken met Stijn op 8 oktober 2026). 'fysiek' betekent: op kantoor bij GKR.
    public const BUFFER_MINUTES = [
        'telefoon' => 30,
        'online'   => 30,
        'fysiek'   => 60,
    ];

    // Een afspraak op kantoor begint niet voor dit tijdstip. Het portaal werkt
    // met hele uren, dus in de praktijk is 10:00 het eerste tijdslot.
    public const OFFICE_EARLIEST_START = '09:30';

    public const OFFICE_TOO_EARLY_MESSAGE = 'Een afspraak op kantoor kan op zijn vroegst om 10:00 uur. Kies een later tijdslot.';

    // Zo heet elk soort afspraak in het portaal en in de mail
    public const TYPE_LABELS = [
        'telefoon' => 'Telefonisch',
        'online'   => 'Online',
        'fysiek'   => 'Op kantoor bij GKR',
    ];

    // Zo ver vooruit toont de adminkalender de bezette tijden uit Outlook (in dagen)
    public const CALENDAR_DAYS_AHEAD = 90;

    // Zo lang slaan we Outlook over nadat het ophalen bij een medewerker is mislukt
    private const OUTLOOK_RETRY_SECONDS = 60;

    public function __construct(private OutlookCalendar $outlook)
    {
    }

    /**
     * Zet een datum + tijdslot ("09:00 - 10:00") om naar een start- en eindtijd.
     * Roep dit pas aan nadat het tijdslot is gevalideerd met Rule::in(SLOTS).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function slotTimes(string $date, string $slot): array
    {
        [$from, $to] = explode(' - ', $slot);

        return [Carbon::parse("{$date} {$from}:00"), Carbon::parse("{$date} {$to}:00")];
    }

    public static function bufferMinutes(?string $type): int
    {
        return self::BUFFER_MINUTES[$type] ?? 0;
    }

    public static function typeLabel(?string $type): string
    {
        return self::TYPE_LABELS[$type] ?? 'Online';
    }

    /**
     * Begint dit soort afspraak te vroeg? Geldt alleen voor afspraken op kantoor.
     */
    public static function startsTooEarly(?string $type, DateTimeInterface $start): bool
    {
        return $type === 'fysiek' && self::toLocal($start)->format('H:i') < self::OFFICE_EARLIEST_START;
    }

    public static function isWeekend(string $date): bool
    {
        try {
            return Carbon::createFromFormat('Y-m-d', $date)->isWeekend();
        } catch (\Throwable) {
            return false; // ongeldige datum: dat vangt de regel date_format al af
        }
    }

    /**
     * Is dit moment al begonnen? (Vandaag om 09:00 boeken terwijl het 14:00 is, mag niet.)
     */
    public static function hasStarted(DateTimeInterface $start): bool
    {
        return self::toLocal($start)->lte(now(self::TIMEZONE));
    }

    /**
     * Welke van deze medewerkers zijn op dit moment al bezet?
     * Geeft de namen terug (leeg = iedereen is vrij).
     *
     * $exceptAppointmentId: deze afspraak zelf niet meetellen
     * (anders botst een afspraak bij het goedkeuren met zichzelf).
     *
     * $withOutlook: ook de Outlook-agenda meetellen. Standaard aan. Zet dit uit
     * bij het bevestigen van een moment dat al was afgesproken: de medewerker
     * kan dat moment zelf al in Outlook hebben gezet, en dan zou de afspraak
     * met zichzelf botsen.
     *
     * $type: het soort afspraak dat we willen plannen (telefoon, online of fysiek).
     * Met een type gelden de buffers: er moet tijd vrij blijven voor en na elke
     * afspraak. Zonder type kijken we alleen naar echte overlap. Dat gebruiken we
     * bij goedkeuren, zodat een eerder gemaakte afspraak altijd afgerond kan worden.
     */
    public function busyEmployeeNames(array $employeeIds, DateTimeInterface $start, DateTimeInterface $end, ?int $exceptAppointmentId = null, bool $withOutlook = true, ?string $type = null): array
    {
        if (empty($employeeIds)) {
            return [];
        }

        $start = self::toLocal($start);
        $end = self::toLocal($end);

        $useBuffers = $type !== null;
        $ownBuffer = self::bufferMinutes($type);
        $widestBuffer = $useBuffers ? max(self::BUFFER_MINUTES) : 0;

        $conflicts = Appointment::query()
            ->whereIn('status', self::BLOCKING_STATUSES)
            ->when($exceptAppointmentId, fn ($query) => $query->whereKeyNot($exceptAppointmentId))
            // Overlap: de bestaande afspraak begint vóór ons einde én eindigt na ons begin.
            // We zoeken eerst ruim (met de grootste buffer) en kijken hieronder per afspraak precies.
            ->where('start_time', '<', $end->copy()->addMinutes($widestBuffer))
            ->where('end_time', '>', $start->copy()->subMinutes($widestBuffer))
            ->whereHas('attendees', fn ($query) => $query->whereIn('users.id', $employeeIds))
            ->with(['attendees' => fn ($query) => $query->select('users.id', 'users.name')])
            ->get()
            ->filter(function ($appointment) use ($start, $end, $useBuffers, $ownBuffer) {
                // Tussen twee afspraken moet de grootste van de twee buffers vrij blijven.
                // Voorbeeld: na een afspraak op kantoor (60 min) kan pas een uur later iets anders.
                $gap = $useBuffers ? max($ownBuffer, self::bufferMinutes($appointment->type)) : 0;

                return self::toLocal($appointment->start_time)->lt($end->copy()->addMinutes($gap))
                    && self::toLocal($appointment->end_time)->gt($start->copy()->subMinutes($gap));
            });

        $names = $conflicts
            ->flatMap(fn ($appointment) => $appointment->attendees)
            ->whereIn('id', $employeeIds)
            ->pluck('name');

        if ($withOutlook) {
            // Ook rond een afspraak in Outlook moet de eigen buffer vrij blijven
            $names = $names->merge($this->outlookBusyNames(
                $employeeIds,
                $start->copy()->subMinutes($ownBuffer),
                $end->copy()->addMinutes($ownBuffer)
            ));
        }

        return $names->unique()->values()->all();
    }

    /**
     * Welke van deze medewerkers hebben op dit moment iets in hun Outlook-agenda?
     * Medewerkers zonder gekoppelde agenda worden overgeslagen.
     *
     * Lukt het ophalen niet (Outlook onbereikbaar, link ingetrokken), dan telt de
     * medewerker als vrij en komt er een regel in de log. Bewuste keuze: klanten
     * moeten kunnen blijven boeken als Microsoft een storing heeft. Dubbele
     * afspraken binnen het portaal zelf blijven altijd geblokkeerd.
     */
    public function outlookBusyNames(array $employeeIds, DateTimeInterface $start, DateTimeInterface $end): array
    {
        if (empty($employeeIds)) {
            return [];
        }

        $employees = User::query()
            ->whereIn('id', $employeeIds)
            ->where('is_admin', true)
            ->whereNotNull('outlook_ics_url')
            ->get();

        $from = self::toLocal($start);
        $to = self::toLocal($end);
        $busy = [];

        foreach ($employees as $employee) {
            if ($this->outlookIntervals($employee, $from, $to)) {
                $busy[] = $employee->name;
            }
        }

        return $busy;
    }

    /**
     * Bezette tijden uit Outlook voor de adminkalender, per dag opgeknipt.
     * Voor alle medewerkers met een gekoppelde agenda, van vandaag tot
     * CALENDAR_DAYS_AHEAD dagen vooruit.
     *
     * Bevat alleen de medewerker en de tijden. Onderwerpen kent het portaal niet,
     * want de agenda's zijn gepubliceerd met alleen vrij/bezet.
     * ALLEEN voor pagina's van GKR: klanten zien uitsluitend BEZET of VRIJ.
     *
     * @return list<array{employee_id: int, employee: string, date: string, time: string}>
     */
    public function outlookCalendarBlocks(): array
    {
        $from = CarbonImmutable::today(self::TIMEZONE);
        $to = $from->addDays(self::CALENDAR_DAYS_AHEAD);

        $employees = User::query()
            ->where('is_admin', true)
            ->whereNotNull('outlook_ics_url')
            ->orderBy('name')
            ->get();

        $blocks = [];

        foreach ($employees as $employee) {
            foreach ($this->outlookIntervals($employee, $from, $to) as $interval) {
                // Een afspraak kan vandaag al bezig zijn of na de periode doorlopen
                $start = $interval['start']->lt($from) ? $from : $interval['start'];
                $end = $interval['end']->gt($to) ? $to : $interval['end'];

                // Een afspraak van meerdere dagen (bijv. vakantie) krijgt op elke dag een blok
                for ($day = $start->startOfDay(); $day->lt($end); $day = $day->addDay()) {
                    $nextDay = $day->addDay();
                    $blockStart = $start->gt($day) ? $start : $day;
                    $blockEnd = $end->lt($nextDay) ? $end : $nextDay;
                    $wholeDay = $blockStart->equalTo($day) && $blockEnd->equalTo($nextDay);

                    $blocks[] = [
                        'employee_id' => $employee->id,
                        'employee'    => $employee->name,
                        'date'        => $day->format('Y-m-d'),
                        'time'        => $wholeDay ? 'Hele dag' : $blockStart->format('H:i') . ' - ' . $blockEnd->format('H:i'),
                    ];
                }
            }
        }

        // Per dag op tijd gesorteerd; "Hele dag" komt bovenaan
        usort($blocks, fn ($a, $b) => [$a['date'], $a['time'] === 'Hele dag' ? '' : $a['time']] <=> [$b['date'], $b['time'] === 'Hele dag' ? '' : $b['time']]);

        return $blocks;
    }

    /**
     * De bezette momenten uit de Outlook-agenda van één medewerker.
     * Lukt het ophalen niet, dan komt er een lege lijst terug (zie outlookBusyNames).
     *
     * @return list<array{start: CarbonImmutable, end: CarbonImmutable}>
     */
    private function outlookIntervals(User $employee, DateTimeInterface $from, DateTimeInterface $to): array
    {
        // Net mislukt? Dan niet bij elk tijdslot opnieuw proberen (dat maakt de kalender traag)
        $failedKey = "outlook_calendar:failed:{$employee->id}";

        if (Cache::has($failedKey)) {
            return [];
        }

        try {
            return $this->outlook->busyIntervals($employee->outlook_ics_url, $from, $to);
        } catch (\Throwable $e) {
            Cache::put($failedKey, true, now()->addSeconds(self::OUTLOOK_RETRY_SECONDS));

            // Alleen onze eigen meldingen loggen: daar staat de link gegarandeerd niet in
            Log::warning('Outlook-agenda kon niet worden gelezen, medewerker telt als vrij', [
                'user_id' => $employee->id,
                'reason'  => ($e instanceof RuntimeException || $e instanceof InvalidArgumentException)
                    ? $e->getMessage()
                    : get_class($e),
            ]);

            return [];
        }
    }

    /**
     * Voert de controle + het opslaan uit terwijl niemand anders tegelijk boekt.
     * Zonder dit kunnen twee klanten op precies hetzelfde moment allebei
     * "vrij" zien en allebei boeken.
     */
    public function withBookingLock(callable $callback): mixed
    {
        return Cache::lock('appointments:booking', 10)->block(5, $callback);
    }

    /**
     * De database bewaart "kloktijd" zonder tijdzone. Deze methode zegt
     * expliciet: dit is Nederlandse tijd.
     */
    public static function toLocal(DateTimeInterface $moment): Carbon
    {
        return Carbon::parse($moment->format('Y-m-d H:i:s'), self::TIMEZONE);
    }

    /**
     * Nederlandse tijd omgerekend naar UTC (nodig voor agendabestanden).
     * 09:00 in de zomer wordt 07:00 UTC, in de winter 08:00 UTC.
     */
    public static function toUtc(DateTimeInterface $moment): Carbon
    {
        return self::toLocal($moment)->utc();
    }
}