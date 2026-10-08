<?php

namespace App\Services\Calendar;

use Carbon\CarbonImmutable;

/**
 * Een "bezet"-blok in één agenda, bijvoorbeeld reistijd.
 */
final readonly class CalendarBlock
{
    public function __construct(
        public string $mailbox,
        public string $subject,
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public ?string $existingId = null,
    ) {}
}
