<?php

namespace Tests\Feature\Appointments;

use Laravel\Sanctum\Sanctum;

/**
 * "Mijn afspraken" of "Iedereen", onthouden per admin-account (stap 4c, ADR-011).
 */
class AgendaScopeTest extends SchedulingTestCase
{
    public function test_mine_shows_only_appointments_where_the_admin_organizes_or_attends(): void
    {
        $york = $this->employee('York', 'york@gkr.nl');
        $owen = $this->employee('Owen', 'owen@gkr.nl');
        $asOrganizer = $this->proposal($york, $this->client(), [$this->monday('10:00')]);
        $asAttendee = $this->proposal($owen, $this->client(), [$this->monday('11:00')]);
        $asAttendee->attendees()->attach($york->id);
        $notMine = $this->proposal($owen, $this->client(), [$this->monday('12:00')]);

        Sanctum::actingAs($york);

        $mine = collect($this->getJson('/api/admin/appointments?scope=mine')->assertOk()->assertJsonPath('scope', 'mine')->json('appointments'))->pluck('id');
        $this->assertEqualsCanonicalizing([$asOrganizer->id, $asAttendee->id], $mine->all());

        $all = collect($this->getJson('/api/admin/appointments?scope=all')->json('appointments'))->pluck('id');
        $this->assertContains($notMine->id, $all);
    }

    public function test_the_saved_preference_applies_without_a_scope_parameter(): void
    {
        $york = $this->employee('York', 'york@gkr.nl');
        $owen = $this->employee('Owen', 'owen@gkr.nl');
        $this->proposal($owen, $this->client(), [$this->monday('12:00')]);

        Sanctum::actingAs($york);
        $this->getJson('/api/admin/appointments')->assertJsonPath('scope', 'all')->assertJsonCount(1, 'appointments');

        $this->patchJson('/api/me/preferences', ['agenda_scope' => 'mine'])->assertOk();

        $this->getJson('/api/admin/appointments')->assertJsonPath('scope', 'mine')->assertJsonCount(0, 'appointments');
        $this->assertSame('mine', $york->fresh()->agenda_scope);
    }

    public function test_the_website_checkbox_saves_the_same_preference(): void
    {
        config(['app.site_password' => null]);
        $york = $this->employee('York', 'york@gkr.nl');

        $this->actingAs($york)
            ->patchJson('/admin/agenda/voorkeur', ['agenda_scope' => 'mine'])
            ->assertOk();

        $this->assertTrue($york->fresh()->prefersOwnAppointmentsOnly());
    }
}
