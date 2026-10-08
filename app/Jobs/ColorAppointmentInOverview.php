<?php

namespace App\Jobs;

use App\Contracts\CalendarProvider;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\User;
use App\Services\Calendar\CalendarCategory;
use App\Services\Calendar\CalendarRejected;
use App\Services\Calendar\CalendarTemporarilyUnavailable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Geeft de kopie van een afspraak in de overzichtsagenda (info@) de kleur van de medewerker(s)
 * en accepteert hem stil (ADR-011).
 *
 * De uitnodiging komt niet direct aan in info@, dus zolang de kopie er nog niet is, probeert
 * deze job het later opnieuw. Lukt het uiteindelijk niet, dan staat de afspraak gewoon zonder
 * kleur in info@; de rest van de sync hangt hier niet van af.
 */
class ColorAppointmentInOverview implements ShouldQueue
{
    use Queueable;

    public int $tries = 6;

    private const BACKOFF = [30, 60, 120, 300, 600];

    public function __construct(public readonly int $appointmentId) {}

    public function handle(CalendarProvider $calendar): void
    {
        $overview = config('calendar.overview_mailbox');
        $appointment = Appointment::with(['organizer', 'attendees'])->find($this->appointmentId);

        if (! $overview || $appointment === null || ! $appointment->ical_uid || ! $appointment->hasStatus(AppointmentStatus::Bevestigd)) {
            return;
        }

        $categories = $appointment->internalParticipants()
            ->map(fn (User $u) => new CalendarCategory($u->name, $u->calendarColorPreset()))
            ->values()
            ->all();

        try {
            $found = $calendar->markInOverview($overview, $appointment->ical_uid, $categories);
        } catch (CalendarRejected) {
            Log::warning('Outlook-koppeling: kleur in overzichtsagenda niet gezet', ['appointment_id' => $appointment->id]);

            return;
        } catch (CalendarTemporarilyUnavailable $e) {
            $this->retryLater($e->retryAfterSeconds);

            return;
        }

        if (! $found) {
            $this->retryLater(null);
        }
    }

    private function retryLater(?int $seconds): void
    {
        if ($this->attempts() >= $this->tries) {
            Log::info('Outlook-koppeling: kopie in overzichtsagenda niet gevonden', ['appointment_id' => $this->appointmentId]);

            return;
        }

        $this->release($seconds ?? self::BACKOFF[min($this->attempts(), count(self::BACKOFF)) - 1]);
    }
}
