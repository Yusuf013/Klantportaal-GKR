<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Maakt de link achter de knop "Zet in Outlook".
 *
 * De link opent Outlook op het web met een nieuwe afspraak waarin alles al is
 * ingevuld: titel, tijd, de klant als genodigde en (bij een online gesprek) de
 * Teams-schakelaar aan. De medewerker klikt daarna zelf op Verzenden.
 *
 * Waarom zo: Outlook maakt de Teams-link zelf aan. Het portaal kan die link dus
 * niet in een eigen uitnodiging zetten. Door Outlook de uitnodiging te laten
 * versturen krijgt de klant een echte uitnodiging met een unieke Teams-link,
 * en staat de afspraak meteen in de agenda van de medewerker.
 *
 * VEILIGHEID: in de link staan e-mailadressen (klant en collega's). Gebruik hem
 * daarom alleen op pagina's voor GKR, nooit op een pagina voor klanten.
 */
class OutlookInvite
{
    // Outlook op het web voor werkaccounts (Microsoft 365)
    private const COMPOSE_URL = 'https://outlook.office.com/calendar/deeplink/compose';

    // Een link mag niet te lang worden, dus de opmerking korten we zo nodig in
    private const MAX_BODY_LENGTH = 600;

    /**
     * $organizer: de medewerker die op de knop klikt. Die wordt zelf de
     * organisator in Outlook en hoeft dus niet als genodigde in de lijst.
     */
    public static function composeUrl(Appointment $appointment, ?User $organizer = null): string
    {
        $appointment->loadMissing(['client', 'attendees']);

        $invitees = collect([$appointment->client])
            ->merge($appointment->attendees)
            ->filter()
            ->reject(fn (User $user) => $organizer !== null && $user->id === $organizer->id)
            ->pluck('email')
            ->filter()
            ->unique(fn (string $email) => strtolower($email))
            ->values();

        $query = [
            'path'    => '/calendar/action/compose',
            'rru'     => 'addevent',
            'subject' => $appointment->title,
            // Outlook verwacht hier UTC: 10:00 in Nederland is in de zomer 08:00 UTC
            'startdt' => AppointmentAvailability::toUtc($appointment->start_time)->format('Y-m-d\TH:i:s\Z'),
            'enddt'   => AppointmentAvailability::toUtc($appointment->end_time)->format('Y-m-d\TH:i:s\Z'),
            'body'    => Str::limit((string) ($appointment->description ?: 'Ingepland via het GKR-klantportaal.'), self::MAX_BODY_LENGTH),
            'to'      => $invitees->implode(','),
        ];

        if ($appointment->type === 'online') {
            // Zet de schakelaar "Teams-vergadering" aan; Outlook maakt dan zelf de link
            $query['online'] = 'true';
        } else {
            $query['location'] = AppointmentAvailability::typeLabel($appointment->type);
        }

        // RFC 3986: spaties worden %20. Met de standaardinstelling worden het plustekens,
        // en die laat Outlook letterlijk in de titel staan.
        return self::COMPOSE_URL . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }
}