<?php

namespace Tests\Feature\Appointments;

use App\Enums\AppointmentStatus;
use Laravel\Sanctum\Sanctum;

/**
 * Klant A kan nooit de afspraak van klant B zien of wijzigen, via de API én via de website
 * (NFR-01/02, CLAUDE.md). Een vreemde afspraak geeft 404, nooit 403.
 */
class CrossClientIsolationTest extends SchedulingTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.site_password' => null]);
    }

    public function test_a_client_cannot_see_or_change_another_clients_appointment_via_the_api(): void
    {
        $owen = $this->employee('Owen', 'owen@gkr.nl');
        $appointment = $this->proposal($owen, $this->client('Klant A'), [$this->monday('10:00')]);
        $optionId = $appointment->options()->first()->id;

        Sanctum::actingAs($this->client('Klant B'));

        $this->getJson("/api/appointments/{$appointment->id}")->assertNotFound();
        $this->postJson("/api/appointments/{$appointment->id}/confirm-option", ['option_id' => $optionId])->assertNotFound();
        $this->postJson("/api/appointments/{$appointment->id}/alternative", ['start_time' => $this->monday('14:00')->toIso8601String()])->assertNotFound();
        $this->postJson("/api/appointments/{$appointment->id}/cancel")->assertNotFound();
        $this->assertSame([], $this->getJson('/api/appointments')->assertOk()->json());

        $this->assertSame(AppointmentStatus::Voorstel->value, $appointment->fresh()->status);
    }

    public function test_a_client_cannot_use_admin_endpoints(): void
    {
        $owen = $this->employee('Owen', 'owen@gkr.nl');
        $appointment = $this->proposal($owen, $this->client('Klant A'), [$this->monday('10:00')]);

        Sanctum::actingAs($this->client('Klant B'));

        $this->getJson('/api/admin/appointments')->assertForbidden();
        $this->postJson("/api/admin/appointments/{$appointment->id}/approve")->assertForbidden();
        $this->getJson('/api/admin/clients')->assertForbidden();
        $this->patchJson('/api/me/preferences', ['agenda_scope' => 'mine'])->assertForbidden();
    }

    public function test_a_client_cannot_book_on_another_clients_project(): void
    {
        $owen = $this->employee('Owen', 'owen@gkr.nl');
        $foreignProject = $this->projectFor($this->client('Klant A'));

        Sanctum::actingAs($this->client('Klant B'));
        $this->postJson('/api/appointments', [
            'type' => 'online', 'project_id' => $foreignProject->id, 'title' => 'Overleg',
            'employee_ids' => [$owen->id], 'start_time' => $this->monday('10:00')->toIso8601String(),
        ])->assertUnprocessable()->assertJsonValidationErrors('project_id');
    }

    public function test_a_client_cannot_confirm_another_clients_proposal_via_the_website(): void
    {
        $owen = $this->employee('Owen', 'owen@gkr.nl');
        $appointment = $this->proposal($owen, $this->client('Klant A'), [$this->monday('10:00')]);

        $this->actingAs($this->client('Klant B'))
            ->postJson("/appointments/{$appointment->id}/confirm-slot", ['option_id' => $appointment->options()->first()->id])
            ->assertNotFound();

        $this->actingAs($this->client('Klant C'))
            ->postJson("/appointments/{$appointment->id}/suggest-alternative", ['date' => '2026-10-12', 'time_slot' => '14:00 - 15:00'])
            ->assertNotFound();

        $this->assertSame(AppointmentStatus::Voorstel->value, $appointment->fresh()->status);
    }

    public function test_the_ics_download_is_only_for_the_client_and_admins(): void
    {
        $owen = $this->employee('Owen', 'owen@gkr.nl');
        $owner = $this->client('Klant A');
        $appointment = $this->confirmed($owen, $owner, $this->monday('10:00'));

        $this->get("/appointments/{$appointment->id}/ics")->assertRedirect('/login');
        $this->actingAs($this->client('Klant B'))->get("/appointments/{$appointment->id}/ics")->assertNotFound();

        $ics = $this->actingAs($owner)->get("/appointments/{$appointment->id}/ics")->assertOk()->getContent();
        // Maandag 10:00 Amsterdam (zomertijd) = 08:00 UTC; seconden, niet de maand.
        $this->assertStringContainsString('DTSTART:20261012T080000Z', $ics);
        $this->assertStringContainsString('DTEND:20261012T090000Z', $ics);

        $this->actingAs($owen)->get("/appointments/{$appointment->id}/ics")->assertOk();
    }
}
