<?php

namespace App\Services\Calendar;

use Carbon\CarbonImmutable;

/**
 * Een afspraak zoals de agenda hem moet tonen. Bevat alleen wat de uitnodiging nodig heeft
 * (dataminimalisatie, skill `external-integration`).
 */
final readonly class CalendarMeeting
{
    /**
     * @param  list<CalendarAttendee>  $requiredAttendees  klant en overige medewerkers
     * @param  list<CalendarAttendee>  $optionalAttendees  de overzichtsagenda (info@)
     */
    public function __construct(
        public string $organizerEmail,
        public string $subject,
        public string $body,
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public string $location,
        public bool $online,
        public array $requiredAttendees,
        public array $optionalAttendees,
        public string $idempotencyKey,
        public ?string $existingId = null,
    ) {}
}
