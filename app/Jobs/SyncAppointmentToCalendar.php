<?php

namespace App\Jobs;

use App\Models\Appointment;
use App\Services\Calendar\CalendarRejected;
use App\Services\Calendar\CalendarSyncService;
use App\Services\Calendar\CalendarTemporarilyUnavailable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Zet een afspraak in Outlook of haalt hem eruit (FR-08, ADR-011), buiten het webverzoek om:
 * een trage of haperende Outlook maakt het platform nooit traag.
 *
 * - Tijdelijke fouten: opnieuw met oplopende wachttijd plus willekeurige spreiding.
 * - Blijvende fouten, of na de laatste poging: `calendar_sync_status = failed`; de admin ziet
 *   dan in gewone taal dat de afspraak nog niet in Outlook staat.
 * - Per afspraak loopt er nooit meer dan één tegelijk; een nieuwe wijziging tijdens het
 *   verwerken wordt wél opnieuw ingepland (UniqueUntilProcessing).
 */
class SyncAppointmentToCalendar implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** Wachttijd in seconden na poging 1, 2, 3 en 4. */
    private const BACKOFF = [60, 300, 900, 3600];

    public function __construct(public readonly int $appointmentId) {}

    public function uniqueId(): string
    {
        return (string) $this->appointmentId;
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('calendar-sync-'.$this->appointmentId))->releaseAfter(30)->expireAfter(180)];
    }

    public function handle(CalendarSyncService $sync): void
    {
        $appointment = Appointment::find($this->appointmentId);

        if ($appointment === null) {
            return;
        }

        try {
            $sync->sync($appointment);
        } catch (CalendarRejected $e) {
            $this->markFailed($appointment, $e->getMessage());
            $this->fail($e);
        } catch (CalendarTemporarilyUnavailable $e) {
            if ($this->attempts() >= $this->tries) {
                $this->markFailed($appointment, $e->getMessage());
                $this->fail($e);

                return;
            }

            $appointment->forceFill(['calendar_sync_error' => $e->getMessage()])->save();
            $this->release($e->retryAfterSeconds ?? self::BACKOFF[min($this->attempts(), count(self::BACKOFF)) - 1] + random_int(0, 30));
        }
    }

    public function failed(?Throwable $exception): void
    {
        $appointment = Appointment::find($this->appointmentId);

        if ($appointment !== null && $appointment->calendar_sync_status !== Appointment::SYNC_FAILED) {
            $this->markFailed($appointment, 'De afspraak kon niet in Outlook worden gezet.');
        }
    }

    private function markFailed(Appointment $appointment, string $reason): void
    {
        $appointment->forceFill([
            'calendar_sync_status' => Appointment::SYNC_FAILED,
            'calendar_sync_error' => mb_substr($reason, 0, 250),
        ])->save();

        // Alleen het id: geen namen, e-mailadressen of onderwerpen in de logs.
        Log::warning('Outlook-koppeling: afspraak niet gesynchroniseerd', ['appointment_id' => $appointment->id]);
    }
}
