<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\ClosedDay;
use App\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Het JavaScript van de afsprakenpagina's bouwt de kalender en de detailpopups in de browser op.
 * Een servertest ziet dat niet uitgevoerd worden; daarom controleert deze test de bron zelf.
 */
class AppointmentPageScriptTest extends TestCase
{
    use RefreshDatabase;

    private const VIEWS = [
        'resources/views/admin/appointments/index.blade.php',
        'resources/views/client/appointments/index.blade.php',
    ];

    public function test_afspraakgegevens_komen_nooit_via_innerhtml_in_de_pagina(): void
    {
        // Titels komen van de klant en namen uit de database: alleen via textContent of
        // createTextNode tonen. Een HTML-template-string met ${app.…} of ${att.…} wordt vroeg of laat
        // aan innerHTML gegeven (direct, of eerst verzameld in een variabele) en voert die tekst dan
        // als HTML uit (stored XSS). Daarom: geen enkele template met HTML-tags én afspraakgegevens.
        foreach (self::VIEWS as $view) {
            $source = file_get_contents(base_path($view));

            preg_match_all('/`([^`]*)`/s', $source, $templates);

            foreach ($templates[1] as $template) {
                if (! str_contains($template, '<')) {
                    continue; // geen HTML, bv. `Klik voor details: ${app.title}` als title-attribuut
                }

                $this->assertDoesNotMatchRegularExpression(
                    '/\$\{\s*(app|att|emp|appointment)\b/',
                    $template,
                    "$view bouwt HTML met afspraakgegevens erin: ".trim(mb_substr($template, 0, 120))
                );
            }
        }
    }

    public function test_adminpagina_haalt_statusgroepen_uit_de_enum(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('admin.appointments.index'))
            ->assertOk()
            ->assertSee('const STATUS_BLOCKING = '.json_encode(AppointmentStatus::blockingValues()), false)
            ->assertSee('const STATUS_TENTATIVE = '.json_encode(AppointmentStatus::tentativeValues()), false);
    }

    public function test_moment_label_is_nederlands_in_de_tijdzone_van_gkr(): void
    {
        $winter = new Appointment(['start_time' => '2026-12-01 13:00:00', 'end_time' => '2026-12-01 14:30:00']);
        $summer = new Appointment(['start_time' => '2026-10-20 09:00:00', 'end_time' => '2026-10-20 10:00:00']);

        $this->assertSame('dinsdag 1 december 2026 om 13:00 - 14:30 uur', $winter->momentLabel());
        $this->assertSame('dinsdag 20 oktober 2026 om 09:00 - 10:00 uur', $summer->momentLabel());
    }

    public function test_beide_detailpopups_krijgen_hetzelfde_door_de_server_opgemaakte_moment(): void
    {
        // Voorheen rekende elk script zelf met tijdzones (twee verschillende kopieën).
        $admin = User::factory()->create(['is_admin' => true]);
        $client = User::factory()->create(['is_admin' => false]);
        $project = Project::forceCreate(['user_id' => $client->id, 'name' => 'Website']);
        $appointment = Appointment::create([
            'user_id' => $client->id, 'project_id' => $project->id, 'title' => 'Review', 'type' => 'online',
            'start_time' => '2026-10-20 10:00:00', 'end_time' => '2026-10-20 11:00:00', 'status' => 'In afwachting',
        ]);
        $appointment->attendees()->attach($admin->id);
        $label = '"moment_label":"dinsdag 20 oktober 2026 om 10:00 - 11:00 uur"';

        $this->actingAs($client)->get(route('client.appointments.index'))->assertOk()->assertSee($label, false);
        $this->actingAs($admin)->get(route('admin.appointments.index'))->assertOk()->assertSee($label, false);

        foreach (self::VIEWS as $view) {
            $this->assertStringNotContainsString('pureDateStr', file_get_contents(base_path($view)), "$view rekent nog zelf met datums.");
        }
    }

    public function test_datumkiezer_krijgt_werkdagen_en_gesloten_dagen_van_de_server(): void
    {
        $client = User::factory()->create(['is_admin' => false]);
        $closed = now()->next(Carbon::WEDNESDAY)->format('Y-m-d');
        ClosedDay::create(['date' => $closed, 'reason' => 'Teamdag']);
        ClosedDay::create(['date' => now()->subWeek()->format('Y-m-d'), 'reason' => 'Voorbij']);
        config(['appointments.working_days' => [1, 2, 3, 4]]);

        $this->actingAs($client)->get(route('client.appointments.index'))
            ->assertOk()
            ->assertSee('const calendarDays = '.json_encode(['working_days' => [1, 2, 3, 4], 'closed' => [$closed]]), false);
    }

    public function test_klantpagina_heeft_een_datumkiezer_zonder_vast_weekend(): void
    {
        $source = file_get_contents(base_path('resources/views/client/appointments/index.blade.php'));

        $this->assertSame(1, substr_count($source, 'function createDatePicker('), 'Eén datumkiezer voor nieuwe afspraak en alternatief.');
        $this->assertStringNotContainsString('renderAltCalendar', $source);
        $this->assertDoesNotMatchRegularExpression('/getDay\(\)\s*===\s*6/', $source, 'Werkdagen komen van de server, niet uit een vast weekend.');
    }
}
