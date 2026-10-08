<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Website en app delen hun validatieregels (AppointmentRules): dezelfde fout geeft dezelfde
 * melding, en de klantpagina laat fouten ook echt zien.
 */
class AppointmentValidationParityTest extends TestCase
{
    use RefreshDatabase;

    private function weekdayAt(string $time): string
    {
        return now()->next(Carbon::TUESDAY)->format('Y-m-d').' '.$time;
    }

    public function test_klant_als_medewerker_geeft_op_website_en_in_app_dezelfde_melding(): void
    {
        $client = User::factory()->create(['is_admin' => false]);
        $project = Project::forceCreate(['user_id' => $client->id, 'name' => 'Website']);
        $otherClient = User::factory()->create(['is_admin' => false]);
        $message = 'Kies GKR-medewerkers uit de lijst.';

        $this->actingAs($client)->post(route('client.appointments.store'), [
            'project_id' => $project->id,
            'type' => 'online',
            'title' => 'Kennismaking',
            'date' => now()->next(Carbon::TUESDAY)->format('Y-m-d'),
            'time_slot' => '10:00 - 11:00',
            'employees' => [$otherClient->id],
        ])->assertSessionHasErrors(['employees.0' => $message]);

        Sanctum::actingAs($client);
        $this->postJson('/api/appointments', [
            'project_id' => $project->id,
            'type' => 'online',
            'title' => 'Kennismaking',
            'start_time' => $this->weekdayAt('10:00'),
            'employee_ids' => [$otherClient->id],
        ])->assertUnprocessable()->assertJsonPath('errors', ['employee_ids.0' => [$message]]);
    }

    public function test_project_van_een_andere_klant_geeft_overal_dezelfde_melding(): void
    {
        $client = User::factory()->create(['is_admin' => false]);
        $foreignProject = Project::forceCreate(['user_id' => User::factory()->create(['is_admin' => false])->id, 'name' => 'Niet van mij']);
        $employee = User::factory()->create(['is_admin' => true]);

        $this->actingAs($client)->post(route('client.appointments.store'), [
            'project_id' => $foreignProject->id,
            'type' => 'online',
            'title' => 'Test',
            'date' => now()->next(Carbon::TUESDAY)->format('Y-m-d'),
            'time_slot' => '10:00 - 11:00',
            'employees' => [$employee->id],
        ])->assertSessionHasErrors(['project_id' => 'Kies een van uw eigen projecten.']);

        Sanctum::actingAs($client);
        $this->postJson('/api/appointments', [
            'project_id' => $foreignProject->id,
            'type' => 'online',
            'title' => 'Test',
            'start_time' => $this->weekdayAt('10:00'),
            'employee_ids' => [$employee->id],
        ])->assertUnprocessable()->assertJsonPath('errors.project_id', ['Kies een van uw eigen projecten.']);
    }

    public function test_admin_kan_op_de_website_geen_project_van_een_andere_klant_voorstellen(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $client = User::factory()->create(['is_admin' => false]);
        $foreignProject = Project::forceCreate(['user_id' => User::factory()->create(['is_admin' => false])->id, 'name' => 'Ander']);

        $this->actingAs($admin)->post(route('admin.appointments.store'), [
            'client_id' => $client->id,
            'project_id' => $foreignProject->id,
            'title' => 'Voorstel',
            'type' => 'online',
            'employees' => [$admin->id],
            'proposal_dates' => [['date' => now()->next(Carbon::TUESDAY)->format('Y-m-d'), 'time_slot' => '10:00 - 11:00']],
        ])->assertSessionHasErrors(['project_id' => 'Kies een project van deze klant.']);

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_klantpagina_toont_een_weigering_en_een_invoerfout(): void
    {
        $client = User::factory()->create(['is_admin' => false]);
        $project = Project::forceCreate(['user_id' => $client->id, 'name' => 'Website']);
        $employee = User::factory()->create(['is_admin' => true]);

        // Weigering uit de afsprakenlogica: zaterdag is geen werkdag.
        $this->actingAs($client)
            ->from(route('client.appointments.index'))
            ->followingRedirects()
            ->post(route('client.appointments.store'), [
                'project_id' => $project->id,
                'type' => 'online',
                'title' => 'Test',
                'date' => now()->next(Carbon::SATURDAY)->format('Y-m-d'),
                'time_slot' => '10:00 - 11:00',
                'employees' => [$employee->id],
            ])
            ->assertOk()
            ->assertSee('Dat lukte niet')
            ->assertSee('Kies een moment op een werkdag');

        // Invoerfout uit de validatie.
        $this->actingAs($client)
            ->from(route('client.appointments.index'))
            ->followingRedirects()
            ->post(route('client.appointments.store'), [
                'project_id' => $project->id,
                'type' => 'online',
                'title' => 'Test',
                'date' => now()->next(Carbon::TUESDAY)->format('Y-m-d'),
                'time_slot' => '10:00 - 11:00',
                'employees' => [],
            ])
            ->assertOk()
            ->assertSee('Kies minstens één GKR-medewerker.');
    }
}
