<?php

namespace App\Exceptions\Appointments;

/**
 * Een collega plant op precies hetzelfde moment dezelfde medewerker in. 409 Conflict.
 */
class SchedulingInProgress extends AppointmentException
{
    public function __construct()
    {
        parent::__construct('Iemand anders is op dit moment dezelfde agenda aan het plannen. Probeer het over een paar seconden opnieuw.');
    }

    protected function httpStatus(): int
    {
        return 409;
    }
}
