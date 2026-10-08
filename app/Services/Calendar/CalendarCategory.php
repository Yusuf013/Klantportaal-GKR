<?php

namespace App\Services\Calendar;

/**
 * Een gekleurd label in de overzichtsagenda, bijvoorbeeld "Owen" in blauw.
 */
final readonly class CalendarCategory
{
    public function __construct(
        public string $name,
        public string $colorPreset,
    ) {}
}
