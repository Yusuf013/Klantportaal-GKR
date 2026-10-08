<?php

namespace Tests\Feature\Calendar;

use App\Enums\AppointmentStatus;
use App\Jobs\ColorAppointmentInOverview;
use App\Jobs\SyncAppointmentToCalendar;
use App\Models\Appointment;
use App\Services\Calendar\CalendarRejected;
use App\Services\Calendar\CalendarSyncService;
use App\Services\Calendar\CalendarTemporarilyUnavailable;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Appointments\SchedulingTestCase;

/**
 * Wat er gebeurt als Outlook hapert (skill `external-integration`, ADR-011): opnieuw proberen
 * bij tijdelijke fouten, en na de laatste poging een melding in gewone taal voor de admin.
 */
class CalendarSyncJobTest extends SchedulingTestCase
{
    private function approvedAppointment(): Appointment
    {
        $owen = $this->employee('Owen', 'owen@gkr.nl');

        return $this->confirmed($owen, $this->client(), $this->monday('10:00'), [
            'calendar_sync_status' => Appointment::SYNC_PENDING,
        ]);
    }

    private function runJob(Appointment $appointment, int $attempt = 1): SyncAppointmentToCalendar
    {
        $job = (new SyncAppointmentToCalendar($appointment->id))->withFakeQueueInteractions();
        $job->job->attempts = $attempt;
        $job->handle(app(CalendarSyncService::class));

        return $job;
    }

    public function test_a_temporary_problem_is_retried_later_and_keeps_the_status_pending(): void
    {
        $appointment = $this->approvedAppointment();
        $this->calendar->failWith(new CalendarTemporarilyUnavailable('Outlook is tijdelijk niet beschikbaar.', 42));

        $this->runJob($appointment)->assertReleased(42);

        $this->assertSame(Appointment::SYNC_PENDING, $appointment->fresh()->calendar_sync_status);
    }

    public function test_after_the_last_attempt_the_appointment_is_marked_failed(): void
    {
        $appointment = $this->approvedAppointment();
        $this->calendar->failWith(new CalendarTemporarilyUnavailable('Outlook is tijdelijk niet beschikbaar.'));

        $this->runJob($appointment, attempt: 5)->assertFailed();

        $this->assertSame(Appointment::SYNC_FAILED, $appointment->fresh()->calendar_sync_status);
    }

    public function test_a_permanent_problem_fails_immediately(): void
    {
        $appointment = $this->approvedAppointment();
        $this->calendar->failWith(new CalendarRejected('Outlook weigerde: afspraak aanmaken.'));

        $this->runJob($appointment)->assertFailed();

        $fresh = $appointment->fresh();
        $this->assertSame(Appointment::SYNC_FAILED, $fresh->calendar_sync_status);
        $this->assertSame('Outlook weigerde: afspraak aanmaken.', $fresh->calendar_sync_error);
    }

    public function test_the_admin_app_shows_a_plain_language_message_for_a_failed_sync(): void
    {
        $appointment = $this->approvedAppointment();
        $appointment->update(['calendar_sync_status' => Appointment::SYNC_FAILED]);

        Sanctum::actingAs($appointment->organizer);
        $this->getJson("/api/appointments/{$appointment->id}")
            ->assertOk()
            ->assertJsonPath('calendar_sync_status', 'failed')
            ->assertJsonPath('calendar_sync_message', 'Deze afspraak staat nog niet in Outlook. We konden hem er niet in zetten; zet hem zelf in je agenda of vraag de beheerder de koppeling te controleren.');

        // Een klant ziet deze interne status niet.
        Sanctum::actingAs($appointment->client);
        $this->getJson("/api/appointments/{$appointment->id}")
            ->assertOk()
            ->assertJsonMissingPath('calendar_sync_status');
    }

    public function test_an_appointment_without_any_gkr_employee_cannot_be_synced(): void
    {
        $appointment = Appointment::factory()->status(AppointmentStatus::Bevestigd)->at($this->monday('10:00'))->create();

        $this->runJob($appointment)->assertFailed();

        $this->assertSame('Er is geen GKR-medewerker aan deze afspraak gekoppeld.', $appointment->fresh()->calendar_sync_error);
    }

    public function test_retrying_after_a_timeout_does_not_create_a_second_meeting(): void
    {
        $appointment = $this->approvedAppointment();

        // Eerste poging: event aangemaakt, maar het antwoord kwam nooit aan.
        $this->runJob($appointment);
        $appointment->forceFill(['outlook_event_id' => null])->save();

        $this->runJob($appointment->fresh());

        $this->assertCount(1, $this->calendar->activeMeetings());
    }

    public function test_the_overview_colour_job_retries_while_the_copy_has_not_arrived(): void
    {
        $appointment = $this->approvedAppointment();
        $this->runJob($appointment);
        $this->calendar->overviewCopyArrives(false);

        $job = (new ColorAppointmentInOverview($appointment->id))->withFakeQueueInteractions();
        $job->handle($this->calendar);
        $job->assertReleased(30);

        $this->calendar->overviewCopyArrives(true);
        $job = (new ColorAppointmentInOverview($appointment->id))->withFakeQueueInteractions();
        $job->handle($this->calendar);
        $job->assertNotReleased();

        $this->assertSame(['Owen'], $this->calendar->overview[$appointment->fresh()->ical_uid]);
    }

    public function test_without_an_overview_mailbox_info_is_not_invited(): void
    {
        config(['calendar.overview_mailbox' => null]);
        $appointment = $this->approvedAppointment();

        $this->runJob($appointment);

        $this->assertSame([], $this->calendar->activeMeetings()[0]->optionalAttendees);
        $this->assertSame([], $this->calendar->overview);
    }
}
