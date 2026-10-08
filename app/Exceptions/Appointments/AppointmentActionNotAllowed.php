<?php

namespace App\Exceptions\Appointments;

/**
 * De actie past niet bij de huidige stand van de afspraak (bijvoorbeeld een al geannuleerde
 * afspraak bevestigen). 422.
 */
class AppointmentActionNotAllowed extends AppointmentException
{
    protected function httpStatus(): int
    {
        return 422;
    }
}
