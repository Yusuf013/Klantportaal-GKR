<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentOption;
use App\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Test dat het soort afspraak (telefoon, online, op kantoor) meetelt als een klant
 * zelf een ander moment voorstelt, en dat de klant overal "Op kantoor bij GKR" leest.
 */
class AppointmentAlternativeTypeTest extends TestCase
{
    use RefreshDatabase;

    // ---------- Hulpfuncties ----------

    protected function setUp(): void
    {
        parent::setUp();

        // Geen enkele medewerker heeft hier een Outlook-agenda, dus er hoort niets naar buiten te gaan
        Http::fake();
    }

    private function client(): User
    {
        return User::factory()->create(['is_admin' => false]);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    // Altijd een doordeweekse dag in de toekomst
    private function weekday(): string
    {
        return now()->next(Carbon::TUESDAY)->format('Y-m-d');
    }

    private function appointment(User $client, string $type, string $status, User $employee): Appointment
    {
        $project = Project::forceCreate(['user_id' => $client->id, 'name' => 'Testproject']);
        $date = $this->weekday();

        $appointment = Appointment::create([
            'user_id'    => $client->id,
            'project_id' => $project->id,
            'title'      => 'Testgesprek',
            'type'       => $type,
            'start_time' => "{$date} 10:00:00",
            'end_time'   => "{$date} 11:00:00",
            'status'     => $status,
        ]);

        $appointment->attendees()->attach([$employee->id]);

        if ($status === 'Voorstel') {
            AppointmentOption::create([
                'appointment_id' => $appointment->id,
                'start_time'     => "{$date} 14:00:00",
                'end_time'       => "{$date} 15:00:00",
            ]);
        }

        return $appointment;
    }

    /**
     * Wat de pagina meegeeft aan de kiezer "ander moment voorstellen".
     */
    private function pickerArguments(string $html): string
    {
        $this->assertSame(1, preg_match('/onclick="openAlternativeDatePicker\(([^"]*)\)"/', $html, $match), 'De knop om een ander moment voor te stellen staat niet op de pagina.');

        return $match[1];
    }

    // ---------- 1. Het soort afspraak gaat mee naar de kiezer ----------

    public function test_kiezer_krijgt_het_soort_afspraak_van_het_voorstel_mee(): void
    {
        $client = $this->client();
        $this->appointment($client, 'fysiek', 'Voorstel', $this->admin());

        $html = $this->actingAs($client)->get(route('client.appointments.index'))->assertOk()->getContent();

        $this->assertStringContainsString('fysiek', $this->pickerArguments($html));
    }

    public function test_bij_een_online_voorstel_krijgt_de_kiezer_online_mee(): void
    {
        $client = $this->client();
        $this->appointment($client, 'online', 'Voorstel', $this->admin());

        $html = $this->actingAs($client)->get(route('client.appointments.index'))->assertOk()->getContent();

        $arguments = $this->pickerArguments($html);

        $this->assertStringContainsString('online', $arguments);
        $this->assertStringNotContainsString('fysiek', $arguments);
    }

    public function test_de_kiezer_stuurt_het_soort_afspraak_mee_bij_het_controleren(): void
    {
        $client = $this->client();
        $this->appointment($client, 'fysiek', 'Voorstel', $this->admin());

        $this->actingAs($client)->get(route('client.appointments.index'))
            ->assertOk()
            ->assertSee('type: altProposalConfig.type', false);
    }

    // ---------- 2. De server gebruikt dat soort ook echt ----------

    public function test_op_kantoor_om_negen_uur_is_bezet_en_online_is_vrij(): void
    {
        $client = $this->client();
        $employee = $this->admin();

        $check = fn (string $type) => $this->actingAs($client)->postJson(route('client.appointments.check'), [
            'employee_id' => $employee->id,
            'date'        => $this->weekday(),
            'time_slot'   => '09:00 - 10:00',
            'type'        => $type,
        ]);

        // Op kantoor kan pas vanaf 10:00, online mag wel om 09:00
        $check('fysiek')->assertOk()->assertJson(['status' => 'conflict']);
        $check('online')->assertOk()->assertJson(['status' => 'available']);
    }

    // ---------- 3. De naam die de klant leest ----------

    public function test_klant_leest_op_kantoor_bij_gkr_en_niet_fysiek(): void
    {
        $client = $this->client();
        $this->appointment($client, 'fysiek', 'Bevestigd', $this->admin());

        $this->actingAs($client)->get(route('client.appointments.index'))
            ->assertOk()
            ->assertSee('Op kantoor bij GKR')
            ->assertDontSee('Fysiek');
    }
}