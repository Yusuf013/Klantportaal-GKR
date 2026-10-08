<?php

namespace App\Services\Calendar;

final readonly class CalendarAttendee
{
    public function __construct(
        public string $email,
        public string $name,
    ) {}
}
