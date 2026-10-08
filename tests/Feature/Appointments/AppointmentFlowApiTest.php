<?php

namespace Tests\Feature\Appointments;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\ClosedDay;
use Laravel\Sanctum\Sanctum;

/**
 * De hele route van een afspraak via de API, tot en met Outlook (FR-08, ADR-011).
 */
class AppointmentFlowApiTest extends SchedulingTestCase
{
    public function test_admin_proposal_client_choice_and_approval_put_the_meeting_in_outlook(): void
    {
        $owen = $this->employee('Owen', 'owen@gkr.nl');
        $noah = $this->employee('Noah', 'noah@gkr.nl');
        $client = $this->client('Bakkerij Jansen');
        $project = $this->projectFor($client);

        Sanctum::actingAs($owen);
        $proposal = $this->postJson('/api/admin/appointments', [
            'client_id' => $client->id,
            'project_id' => $project->id,
            'type' => Appointment::TYPE_ONLINE,
            'duration_minutes' => 60,
            'title' => 'Kwartaalreview',
            'employee_ids' => [$noah->id],
            'options' => [$this->monday('10:00')->toIso8601String(), $this->monday('14:00')->toIso8601String()],
        ])->assertCreated()
            ->assertJsonPath('status', AppointmentStatus::Voorstel->value)
            ->assertJsonPath('organizer.name', 'Owen')
            ->assertJsonCount(2, 'options')
            ->assertJsonCount(2, 'participants');

        $id = $proposal->json('id');
        $optionId = $proposal->json('options.1.id');

        // Een voorstel staat nog niet in Outlook.
        $this->assertSame([], $this->calendar->activeMeetings());

        Sanctum::actingAs($client);
        $this->postJson("/api/appointments/{$id}/confirm-option", ['option_id' => $optionId])
            ->assertOk()
            ->assertJsonPath('status', AppointmentStatus::BevestigdDoorKlant->value);

        Sanctum::actingAs($owen);
        $this->postJson("/api/admin/appointments/{$id}/approve")
            ->assertOk()
            ->assertJsonPath('appointment.status', AppointmentStatus::Bevestigd->value)
            ->assertJsonPath('notice', null);

        $meetings = $this->calendar->activeMeetings();
        $this->assertCount(1, $meetings);
        $meeting = $meetings[0];

        // Uitnodiging vanaf de werkmail van de medewerker die het voorstel deed.
        $this->assertSame('owen@gkr.nl', $meeting->organizerEmail);
        $this->assertEquals($this->monday('14:00'), $meeting->start);
        $this->assertTrue($meeting->online);
        $this->assertEqualsCanonicalizing(
            [$client->email, 'noah@gkr.nl'],
            array_map(fn ($a) => $a->email, $meeting->requiredAttendees),
        );
        $this->assertSame(['info@gkr.nl'], array_map(fn ($a) => $a->email, $meeting->optionalAttendees));

        $appointment = Appointment::find($id);
        $this->assertSame(Appointment::SYNC_SYNCED, $appointment->calendar_sync_status);
        $this->assertNotNull($appointment->outlook_event_id);
        $this->assertStringStartsWith('https://example.invalid/teams/', $appointment->online_meeting_url);

        // Overzichtsagenda: gekleurd met beide medewerkers.
        $this->assertEqualsCanonicalizing(['Owen', 'Noah'], $this->calendar->overview[$appointment->ical_uid]);
    }

    public function test_client_request_uses_the_first_chosen_employee_as_organizer(): void
    {
        $owen = $this->employee('Owen', 'owen@gkr.nl');
        $york = $this->employee('York', 'york@gkr.nl');
        $client = $this->client();
        $project = $this->projectFor($client);

        Sanctum::actingAs($client);
        $id = $this->postJson('/api/appointments', [
            'type' => Appointment::TYPE_TELEFOON,
            'project_id' => $project->id,
            'title' => 'Vraag over de offerte',
            'employee_ids' => [$york->id, $owen->id],
            'start_time' => $this->monday('11:00')->toIso8601String(),
        ])->assertCreated()
            ->assertJsonPath('status', AppointmentStatus::InAfwachting->value)
            ->assertJsonPath('duration_minutes', 60)
            ->assertJsonPath('organizer.name', 'York')
            ->json('id');

        Sanctum::actingAs($owen);
        $this->postJson("/api/admin/appointments/{$id}/approve")->assertOk();

        $this->assertSame('york@gkr.nl', $this->calendar->activeMeetings()[0]->organizerEmail);
    }

    public function test_a_physical_appointment_on_location_gets_travel_blocks_for_each_employee(): void
    {
        $owen = $this->employee('Owen', 'owen@gkr.nl');
        $stijn = $this->employee('Stijn', 'stijn@gkr.nl');
        $client = $this->client();
        $appointment = $this->proposal($owen, $client, [$this->monday('12:00')], [
            'type' => Appointment::TYPE_FYSIEK,
            'location' => Appointment::LOCATION_OP_LOCATIE,
            'travel_minutes' => 45,
        ]);
        $appointment->attendees()->sync([$owen->id, $stijn->id]);

        Sanctum::actingAs($client);
        $this->postJson("/api/appointments/{$appointment->id}/confirm-option", ['option_id' => $appointment->options()->first()->id])->assertOk();
        Sanctum::actingAs($owen);
        $this->postJson("/api/admin/appointments/{$appointment->id}/approve")->assertOk();

        $blocks = collect($this->calendar->blocks);
        $this->assertCount(4, $blocks);
        $this->assertEqualsCanonicalizing(['owen@gkr.nl', 'owen@gkr.nl', 'stijn@gkr.nl', 'stijn@gkr.nl'], $blocks->pluck('mailbox')->all());
        $this->assertTrue($blocks->contains(fn ($b) => $b->start->eq($this->monday('11:15')) && $b->end->eq($this->monday('12:00'))));
        $this->assertTrue($blocks->contains(fn ($b) => $b->start->eq($this->monday('13:00')) && $b->end->eq($this->monday('13:45'))));
        $this->assertSame('Op locatie bij de klant', $this->calendar->activeMeetings()[0]->location);
    }

    public function test_client_cancellation_cancels_the_outlook_meeting_and_removes_travel_blocks(): void
    {
        $owen = $this->employee('Owen', 'owen@gkr.nl');
        $client = $this->client();
        $appointment = $this->proposal($owen, $client, [$this->monday('12:00')], [
            'type' => Appointment::TYPE_FYSIEK,
            'location' => Appointment::LOCATION_OP_LOCATIE,
            'travel_minutes' => 30,
        ]);

        Sanctum::actingAs($client);
        $this->postJson("/api/appointments/{$appointment->id}/confirm-option", ['option_id' => $appointment->options()->first()->id]);
        Sanctum::actingAs($owen);
        $this->postJson("/api/admin/appointments/{$appointment->id}/approve");
        $this->assertCount(1, $this->calendar->activeMeetings());
        $this->assertCount(2, $this->calendar->blocks);

        Sanctum::actingAs($client);
        $this->postJson("/api/appointments/{$appointment->id}/cancel")
            ->assertOk()
            ->assertJsonPath('status', AppointmentStatus::Geannuleerd->value)
            ->assertJsonPath('can_cancel', false);

        $this->assertSame([], $this->calendar->activeMeetings());
        $this->assertSame([], $this->calendar->blocks);
        $this->assertSame(0, $appointment->calendarEvents()->count());
    }

    public function test_cancelling_before_approval_never_touches_outlook(): void
    {
        $owen = $this->employee('Owen', 'owen@gkr.nl');
        $client = $this->client();
        $appointment = $this->proposal($owen, $client, [$this->monday('12:00')]);

        Sanctum::actingAs($client);
        $this->postJson("/api/appointments/{$appointment->id}/cancel")->assertOk();

        $this->assertSame([], $this->calendar->meetings);
        $this->assertSame(Appointment::SYNC_NOT_REQUIRED, $appointment->fresh()->calendar_sync_status);
    }

    public function test_a_client_asking_for_an_on_location_meeting_is_asked_to_request_a_call(): void
    {
        $owen = $this->employee('Owen', 'owen@gkr.nl');
        $client = $this->client();

        Sanctum::actingAs($client);
        $this->postJson('/api/appointments', [
            'type' => Appointment::TYPE_FYSIEK,
            'location' => Appointment::LOCATION_OP_LOCATIE,
            'project_id' => $this->projectFor($client)->id,
            'title' => 'Bezoek',
            'employee_ids' => [$owen->id],
            'start_time' => $this->monday('11:00')->toIso8601String(),
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Voor een afspraak op locatie bellen we u graag even. Dien daarvoor een belverzoek in.');

        $this->postJson('/api/callback-requests', ['phone' => '06 12345678', 'note' => 'Graag bij ons op kantoor'])
            ->assertCreated()
            ->assertJsonPath('status', 'open');

        Sanctum::actingAs($owen);
        $this->getJson('/api/admin/callback-requests')
            ->assertOk()
            ->assertJsonPath('0.phone', '06 12345678')
            ->assertJsonPath('0.client.name', $client->name);
    }

    public function test_requests_outside_working_hours_or_on_closed_days_are_refused_in_plain_language(): void
    {
        $owen = $this->employee('Owen', 'owen@gkr.nl');
        $client = $this->client();
        $project = $this->projectFor($client);
        ClosedDay::create(['date' => '2026-10-13', 'reason' => 'Studiedag']);
        Sanctum::actingAs($client);

        $payload = fn ($start) => [
            'type' => Appointment::TYPE_ONLINE, 'project_id' => $project->id, 'title' => 'Overleg',
            'employee_ids' => [$owen->id], 'start_time' => $start->toIso8601String(),
        ];

        $this->postJson('/api/appointments', $payload($this->monday('17:00')))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Kies een moment op een werkdag tussen 09:00 en 17:00.');

        $this->postJson('/api/appointments', $payload($this->monday('10:00')->addDay()))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'GKR is op deze dag gesloten (Studiedag). Kies een andere dag.');
    }

    public function test_the_admin_proposal_project_must_belong_to_the_chosen_client(): void
    {
        $owen = $this->employee('Owen', 'owen@gkr.nl');
        $client = $this->client();
        $otherProject = $this->projectFor($this->client('Ander bedrijf'));

        Sanctum::actingAs($owen);
        $this->postJson('/api/admin/appointments', [
            'client_id' => $client->id,
            'project_id' => $otherProject->id,
            'type' => Appointment::TYPE_ONLINE,
            'duration_minutes' => 60,
            'title' => 'Overleg',
            'options' => [$this->monday('10:00')->toIso8601String()],
        ])->assertUnprocessable()->assertJsonValidationErrors('project_id');
    }

    public function test_employees_list_includes_their_calendar_colour_and_admins_can_change_it(): void
    {
        $owen = $this->employee('Owen', 'owen@gkr.nl');
        $noah = $this->employee('Noah', 'noah@gkr.nl');

        Sanctum::actingAs($this->client());
        $colors = collect($this->getJson('/api/employees')->assertOk()->json())->pluck('color');
        $this->assertCount(2, $colors->unique(), 'collega\'s krijgen automatisch verschillende kleuren');

        Sanctum::actingAs($owen);
        $this->patchJson("/api/admin/employees/{$noah->id}", ['calendar_color' => 'preset8'])
            ->assertOk()
            ->assertJsonPath('color', config('calendar.colors.preset8'));
    }

    public function test_contact_endpoint_reports_whether_gkr_can_be_called_now(): void
    {
        config(['appointments.contact.phone' => '010 1234567']);
        Sanctum::actingAs($this->client());

        // Woensdag 08:00: vóór werktijd.
        $this->getJson('/api/contact')->assertOk()->assertJsonPath('available_now', false)->assertJsonPath('label', 'Nu niet beschikbaar');

        $this->travelTo($this->monday('10:00'));
        $this->getJson('/api/contact')->assertOk()->assertJsonPath('available_now', true)->assertJsonPath('phone', '010 1234567');
    }
}
