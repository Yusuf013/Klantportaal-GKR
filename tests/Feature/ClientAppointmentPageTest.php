<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentOption;
use App\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientAppointmentPageTest extends TestCase
{
    use RefreshDatabase;

    private function appointmentFor(User $client, User $employee, string $status): Appointment
    {
        $date = now()->next(Carbon::TUESDAY)->format('Y-m-d');
        $project = Project::forceCreate(['user_id' => $client->id, 'name' => 'Testproject']);

        $appointment = Appointment::create([
            'user_id'    => $client->id,
            'project_id' => $project->id,
            'title'      => 'Maandelijkse review',
            'type'       => 'online',
            'start_time' => "{$date} 10:00:00",
            'end_time'   => "{$date} 11:00:00",
            'status'     => $status,
        ]);
        $appointment->attendees()->attach($employee->id);

        if ($status === 'Voorstel') {
            AppointmentOption::create([
                'appointment_id' => $appointment->id,
                'start_time'     => "{$date} 14:00:00",
                'end_time'       => "{$date} 15:00:00",
            ]);
        }

        return $appointment;
    }

    public function test_pagina_bevat_geen_emailadressen_van_medewerkers(): void
    {
        $client = User::factory()->create(['is_admin' => false]);
        $employee = User::factory()->create(['is_admin' => true, 'email' => 'medewerker@example.com']);
        $this->appointmentFor($client, $employee, 'Bevestigd');

        $this->actingAs($client)->get(route('client.appointments.index'))
            ->assertOk()
            ->assertDontSee('medewerker@example.com');
    }

    public function test_gekozen_voorstel_blijft_zichtbaar_voor_de_klant(): void
    {
        $client = User::factory()->create(['is_admin' => false]);
        $this->appointmentFor($client, User::factory()->create(['is_admin' => true]), 'Bevestigd door klant');

        $this->actingAs($client)->get(route('client.appointments.index'))
            ->assertOk()
            ->assertSee('Maandelijkse review')
            ->assertSee('Gekozen, wacht op GKR');
    }

    public function test_naam_met_html_wordt_nooit_als_code_in_de_pagina_gezet(): void
    {
        $client = User::factory()->create(['is_admin' => false]);
        $employee = User::factory()->create(['is_admin' => true, 'name' => "<script>alert('x')</script>"]);
        $this->appointmentFor($client, $employee, 'Voorstel');

        $this->actingAs($client)->get(route('client.appointments.index'))
            ->assertOk()
            ->assertDontSee("<script>alert('x')</script>", false);
    }
}