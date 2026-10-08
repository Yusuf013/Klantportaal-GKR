<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminAppointmentPageTest extends TestCase
{
    use RefreshDatabase;

    private function appointment(User $client, User $employee, string $status, string $title = 'Maandelijkse review'): Appointment
    {
        $date = now()->next(Carbon::TUESDAY)->format('Y-m-d');
        $project = Project::forceCreate(['user_id' => $client->id, 'name' => 'Testproject']);

        $appointment = Appointment::create([
            'user_id'    => $client->id,
            'project_id' => $project->id,
            'title'      => $title,
            'type'       => 'online',
            'start_time' => "{$date} 10:00:00",
            'end_time'   => "{$date} 11:00:00",
            'status'     => $status,
        ]);
        $appointment->attendees()->attach($employee->id);

        return $appointment;
    }

    public function test_titel_van_klant_wordt_nooit_als_code_in_de_adminpagina_gezet(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $client = User::factory()->create(['is_admin' => false]);
        $this->appointment($client, $admin, 'In afwachting', '<img src=x onerror=alert(1)>');

        $this->actingAs($admin)->get(route('admin.appointments.index'))
            ->assertOk()
            ->assertDontSee('<img src=x onerror=alert(1)>', false);
    }

    public function test_foutmelding_bij_goedkeuren_is_zichtbaar(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['is_admin' => true]);
        $client = User::factory()->create(['is_admin' => false]);

        // Twee afspraken op hetzelfde moment bij dezelfde medewerker
        $this->appointment($client, $admin, 'Bevestigd');
        $pending = $this->appointment($client, $admin, 'In afwachting');

        $this->actingAs($admin)
            ->from(route('admin.appointments.index'))
            ->followingRedirects()
            ->patch(route('admin.appointments.approve', $pending))
            ->assertOk()
            ->assertSee('Dat lukte niet')
            ->assertSee('al bezet');
    }

    public function test_projecten_weten_bij_welke_klant_ze_horen(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $client = User::factory()->create(['is_admin' => false]);
        Project::forceCreate(['user_id' => $client->id, 'name' => 'Website']);

        $this->actingAs($admin)->get(route('admin.appointments.index'))
            ->assertOk()
            ->assertSee('data-client="' . $client->id . '"', false);
    }
}