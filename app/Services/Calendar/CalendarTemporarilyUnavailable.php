<?php

namespace App\Services\Calendar;

use RuntimeException;

/**
 * De agenda is tijdelijk niet bereikbaar (timeout, te veel verzoeken, storing). Later opnieuw
 * proberen is zinvol.
 */
class CalendarTemporarilyUnavailable extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $retryAfterSeconds = null)
    {
        parent::__construct($message);
    }
}
