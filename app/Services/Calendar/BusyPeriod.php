<?php

namespace App\Services\Calendar;

use Carbon\CarbonImmutable;

final readonly class BusyPeriod
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public BusyKind $kind,
    ) {}

    public function overlaps(CarbonImmutable $start, CarbonImmutable $end): bool
    {
        return $this->start < $end && $this->end > $start;
    }
}
