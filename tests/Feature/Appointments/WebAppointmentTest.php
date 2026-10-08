<?php

namespace Tests\Feature\Appointments;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Services\Calendar\BusyKind;
use App\Services\Calendar\BusyPeriod;

/**
 * De bestaande website blijft werken via de gedeelde service, en krijgt zo ook Outlook-sync en
 * Outlook-beschikbaarheid (ADR-011; FR-10: schermen niet herontworpen).
 */
class WebAppointmentTest extends SchedulingTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.site_password' => null]);
    }

    public function test_admin_web_form_creates_a_proposal_with_the_admin_as_organizer(): void
    {
        $owen = $this->employee('Owen', 'owen@gkr.nl');
        $noah = $this->employee('Noah', 'noah@gkr.nl');
        $client = $this->client();
        $project = $this->projectFor($client);

        $this->actingAs($owen)->post('/admin/agenda', [
            'client_id' => $client->id,
            'project_id' => $project->id,
            'title' => 'Kick-off',
            'type' => 'online',
            'employees' => [$noah->id, ''],
            'proposal_dates' => [
                ['date' => '2026-10-12', 'time_slot' => '09:00 - 10:00'],
                ['date' => '2026-10-13', 'time_slot' => '15:00 - 16:00'],
                ['date' => '', 'time_slot' => ''],
            ],
        ])->assertRedirect()->assertSessionHas('success');

        $appointment = Appointment::sole();
        $this->assertSame(AppointmentStatus::Voorstel->value, $appointment->status);
        $this->assertSame($owen->id, $appointment->organizer_user_id);
        $this->assertEqualsCanonicalizing([$owen->id, $noah->id], $appointment->attendees->pluck('id')->all());
        $this->assertSame(2, $appointment->options()->count());
    }

    public function test_client_web_request_and_admin_web_approval_sync_to_outlook(): void
    {
        $owen = $this->employee('Owen', 'owen@gkr.nl');
        $client = $this->client();
        $project = $this->projectFor($client);

        $this->actingAs($client)->post('/mijn-agenda', [
            'project_id' => $project->id,
            'type' => 'telefoon',
            'title' => 'Vraag',
            'date' => '2026-10-12',
            'time_slot' => '11:00 - 12:00',
            'employees' => [$owen->id, ''],
        ])->assertRedirect(route('client.appointments.index'));

        $appointment = Appointment::sole();
        $this->assertSame($owen->id, $appointment->organizer_user_id);

        $this->actingAs($owen)->patch("/admin/appointments/{$appointment->id}/approve")
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(AppointmentStatus::Bevestigd->value, $appointment->fresh()->status);
        $this->assertCount(1, $this->calendar->activeMeetings());
    }

    public function test_web_approval_of_a_taken_moment_returns_with_a_plain_language_error(): void
    {
        $owen = $this->employee('Owen', 'owen@gkr.nl');
        $this->confirmed($owen, $this->client(), $this->monday('10:00'));
        $request = Appointment::factory()->status(AppointmentStatus::InAfwachting)->at($this->monday('10:00'))
            ->create(['organizer_user_id' => $owen->id]);
        $request->attendees()->sync([$owen->id]);

        $this->actingAs($owen)->from('/admin/agenda')->patch("/admin/appointments/{$request->id}/approve")
            ->assertRedirect('/admin/agenda')
            ->assertSessionHas('error', 'Owen is op dit moment al bezet. Kies een ander moment.');
    }

    public function test_the_web_availability_check_now_sees_outlook(): void
    {
        $owen = $this->employee('Owen', 'owen@gkr.nl');
        $this->calendar->setBusy('owen@gkr.nl', [new BusyPeriod($this->monday('10:00'), $this->monday('11:00'), BusyKind::Busy)]);

        $this->actingAs($this->client())
            ->postJson('/mijn-agenda/check-beschikbaarheid', ['employee_id' => $owen->id, 'date' => '2026-10-12', 'time_slot' => '10:00 - 11:00'])
            ->assertOk()
            ->assertJsonPath('status', 'conflict');

        $this->actingAs($this->client())
            ->postJson('/mijn-agenda/check-beschikbaarheid', ['employee_id' => $owen->id, 'date' => '2026-10-12', 'time_slot' => '11:00 - 12:00'])
            ->assertOk()
            ->assertJsonPath('status', 'available');
    }

    public function test_the_website_uses_the_configured_working_hours(): void
    {
        $this->withoutVite();
        $owen = $this->employee('Owen', 'owen@gkr.nl');

        $html = $this->actingAs($owen)->get('/admin/agenda')->assertOk()->getContent();

        $this->assertStringContainsString('"16:00 - 17:00"', $html);
        $this->assertStringContainsString('"12:00 - 13:00"', $html);
    }

    public function test_the_client_agenda_page_still_renders(): void
    {
        $this->withoutVite();
        $this->employee('Owen', 'owen@gkr.nl');
        $client = $this->client();
        $this->proposal($this->employee('Noah', 'noah@gkr.nl'), $client, [$this->monday('10:00')]);

        $this->actingAs($client)->get('/mijn-agenda')->assertOk()->assertSee('"16:00 - 17:00"', false);
    }
}
