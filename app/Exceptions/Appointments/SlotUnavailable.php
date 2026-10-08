<?php

namespace App\Exceptions\Appointments;

/**
 * Het gekozen moment is (inmiddels) bezet. 409 Conflict.
 */
class SlotUnavailable extends AppointmentException
{
    public static function forClient(): self
    {
        return new self('Dit moment is net door iemand anders vastgelegd. Kies een van de andere tijden of vraag een nieuw moment aan.');
    }

    public static function forEmployee(string $name): self
    {
        return new self("{$name} is op dit moment al bezet. Kies een ander moment.");
    }

    protected function httpStatus(): int
    {
        return 409;
    }
}
