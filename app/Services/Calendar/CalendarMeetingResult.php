<?php

namespace App\Services\Calendar;

final readonly class CalendarMeetingResult
{
    public function __construct(
        public string $externalId,
        public ?string $iCalUId,
        public ?string $joinUrl,
    ) {}
}
