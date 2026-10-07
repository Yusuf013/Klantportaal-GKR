<?php

namespace App\Services;

use App\Models\Appointment;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Eén centrale plek voor alle regels rond beschikbaarheid van afspraken.
 *
 * Voorheen stonden deze regels verspreid (deels alleen in JavaScript).
 * Nu gebruiken de klant- en admincontroller allebei deze class, zodat
 * de regels overal hetzelfde zijn. Later kan hier ook de Outlook-agenda
 * worden geraadpleegd, zonder dat de controllers hoeven te veranderen.
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
     */
    public function busyEmployeeNames(array $employeeIds, DateTimeInterface $start, DateTimeInterface $end, ?int $exceptAppointmentId = null): array
    {
        if (empty($employeeIds)) {
            return [];
        }

        $conflicts = Appointment::query()
            ->whereIn('status', self::BLOCKING_STATUSES)
            ->when($exceptAppointmentId, fn ($query) => $query->whereKeyNot($exceptAppointmentId))
            // Overlap: de bestaande afspraak begint vóór ons einde én eindigt na ons begin
            ->where('start_time', '<', $end)
            ->where('end_time', '>', $start)
            ->whereHas('attendees', fn ($query) => $query->whereIn('users.id', $employeeIds))
            ->with(['attendees' => fn ($query) => $query->select('users.id', 'users.name')])
            ->get();

        return $conflicts
            ->flatMap(fn ($appointment) => $appointment->attendees)
            ->whereIn('id', $employeeIds)
            ->pluck('name')
            ->unique()
            ->values()
            ->all();
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