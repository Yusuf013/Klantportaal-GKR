<?php

namespace Tests\Feature;

use App\Mail\AppointmentConfirmed;
use App\Models\Appointment;
use App\Models\AppointmentOption;
use App\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Oorspronkelijk geschreven voor Yusufs afsprakenimplementatie (7 oktober 2026). Bij het samenvoegen
 * met de Outlook-koppeling (ADR-011) is de afsprakenlogica van `feature/outlook-koppeling` de basis
 * geworden; deze tests zijn behouden en aangepast aan het antwoordformaat daarvan (melding `error`
 * i.p.v. een validatiefout, 409 bij een bezet moment, .ics achter login i.p.v. ondertekende link,
 * mailinstelling `appointments.confirmation_mail`). Wat ze controleren is ongewijzigd.
 */
class AppointmentSecurityTest extends TestCase
{
    use RefreshDatabase;

    // ---------- Hulpfuncties ----------

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function client(): User
    {
        return User::factory()->create(['is_admin' => false]);
    }

    private function project(User $client): Project
    {
        return Project::forceCreate(['user_id' => $client->id, 'name' => 'Testproject']);
    }

    // Altijd een doordeweekse dag in de toekomst
    private function weekday(): string
    {
        return now()->next(Carbon::TUESDAY)->format('Y-m-d');
    }

    private function appointment(User $client, string $status, string $date, string $slot = '10:00 - 11:00', array $employees = []): Appointment
    {
        [$from, $to] = explode(' - ', $slot);

        $appointment = Appointment::create([
            'user_id'    => $client->id,
            'project_id' => $this->project($client)->id,
            'title'      => 'Testgesprek',
            'type'       => 'online',
            'start_time' => "{$date} {$from}:00",
            'end_time'   => "{$date} {$to}:00",
            'status'     => $status,
        ]);

        $appointment->attendees()->attach(collect($employees)->pluck('id')->all());

        return $appointment;
    }

    private function proposal(User $client, User $employee, string $date): array
    {
        $appointment = $this->appointment($client, 'Voorstel', $date, '10:00 - 11:00', [$employee]);
        $option = AppointmentOption::create([
            'appointment_id' => $appointment->id,
            'start_time'     => "{$date} 14:00:00",
            'end_time'       => "{$date} 15:00:00",
        ]);

        return [$appointment, $option];
    }

    // ---------- 1. Afscherming per klant ----------

    public function test_klant_kan_voorstel_van_andere_klant_niet_bevestigen(): void
    {
        [$appointment, $option] = $this->proposal($this->client(), $this->admin(), $this->weekday());

        $this->actingAs($this->client())
            ->postJson(route('client.appointments.confirmSlot', $appointment), ['option_id' => $option->id])
            ->assertNotFound();

        $this->assertSame('Voorstel', $appointment->fresh()->status);
        $this->assertSame(1, $appointment->options()->count());
    }

    public function test_klant_kan_geen_alternatief_kiezen_voor_andere_klant(): void
    {
        [$appointment] = $this->proposal($this->client(), $this->admin(), $this->weekday());

        $this->actingAs($this->client())
            ->postJson(route('client.appointments.suggest-alternative', $appointment), [
                'date' => $this->weekday(), 'time_slot' => '09:00 - 10:00',
            ])
            ->assertNotFound();

        $this->assertSame('Voorstel', $appointment->fresh()->status);
    }

    public function test_klant_kan_eigen_voorstel_bevestigen(): void
    {
        $client = $this->client();
        [$appointment, $option] = $this->proposal($client, $this->admin(), $this->weekday());

        $this->actingAs($client)
            ->postJson(route('client.appointments.confirmSlot', $appointment), ['option_id' => $option->id])
            ->assertOk()
            ->assertJson(['status' => 'success']);

        $this->assertSame('Bevestigd door klant', $appointment->fresh()->status);
        $this->assertSame(0, $appointment->options()->count());
    }

    public function test_een_voorstel_kan_maar_een_keer_worden_verwerkt(): void
    {
        $client = $this->client();
        [$appointment, $option] = $this->proposal($client, $this->admin(), $this->weekday());
        $appointment->update(['status' => 'Bevestigd']);

        $this->actingAs($client)
            ->postJson(route('client.appointments.confirmSlot', $appointment), ['option_id' => $option->id])
            ->assertStatus(422);

        $this->assertSame('Bevestigd', $appointment->fresh()->status);
    }

    public function test_klant_kan_geen_afspraak_maken_op_project_van_ander(): void
    {
        $client = $this->client();
        $otherProject = $this->project($this->client());

        $this->actingAs($client)->post(route('client.appointments.store'), [
            'project_id' => $otherProject->id,
            'type'       => 'online',
            'title'      => 'Test',
            'date'       => $this->weekday(),
            'time_slot'  => '09:00 - 10:00',
            'employees'  => [$this->admin()->id],
        ])->assertSessionHasErrors('project_id');

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_klant_kan_geen_andere_klant_als_medewerker_kiezen(): void
    {
        $client = $this->client();

        $this->actingAs($client)->post(route('client.appointments.store'), [
            'project_id' => $this->project($client)->id,
            'type'       => 'online',
            'title'      => 'Test',
            'date'       => $this->weekday(),
            'time_slot'  => '09:00 - 10:00',
            'employees'  => [$this->client()->id],
        ])->assertSessionHas('error');

        $this->assertDatabaseCount('appointments', 0);
    }

    // ---------- 2. Agendabestand (.ics) ----------

    public function test_ics_zonder_inloggen_gaat_naar_de_loginpagina(): void
    {
        // Geen ondertekende publieke link meer: het agendabestand staat achter de login en de
        // AppointmentPolicy (ADR-011). De echte agenda-uitnodiging komt via Outlook.
        $appointment = $this->appointment($this->client(), 'Bevestigd', $this->weekday());

        $this->get('/appointments/' . $appointment->id . '/ics')->assertRedirect(route('login'));
    }

    public function test_ics_heeft_de_juiste_tijden_in_zomer_en_winter(): void
    {
        $client = $this->client();

        // 20 oktober = zomertijd (UTC+2), 1 december = wintertijd (UTC+1)
        $summer = $this->appointment($client, 'Bevestigd', '2026-10-20', '09:00 - 10:00');
        $winter = $this->appointment($client, 'Bevestigd', '2026-12-01', '13:00 - 14:00');

        $this->actingAs($client)->get(route('appointments.ics', $summer))
            ->assertOk()
            ->assertSee('DTSTART:20261020T070000Z', false)
            ->assertSee('DTEND:20261020T080000Z', false)
            ->assertSee('UID:appointment-' . $summer->id . '@gkr-klantportaal.nl', false);

        $this->actingAs($client)->get(route('appointments.ics', $winter))
            ->assertOk()
            ->assertSee('DTSTART:20261201T120000Z', false);
    }

    // ---------- 3. Dubbele boekingen ----------

    public function test_aanvraag_naast_een_openstaande_aanvraag_wordt_aangenomen(): void
    {
        // ADR-011, besluit 6 (met York): een aanvraag of voorstel reserveert niets; de harde
        // controle volgt bij het vastleggen (zie test_admin_kan_geen_dubbele_afspraak_goedkeuren).
        $employee = $this->admin();
        $date = $this->weekday();
        $this->appointment($this->client(), 'In afwachting', $date, '09:00 - 10:00', [$employee]);

        $client = $this->client();
        $this->actingAs($client)->post(route('client.appointments.store'), [
            'project_id' => $this->project($client)->id,
            'type'       => 'online',
            'title'      => 'Test',
            'date'       => $date,
            'time_slot'  => '09:00 - 10:00',
            'employees'  => [$employee->id],
        ])->assertSessionHas('success');

        $this->assertDatabaseCount('appointments', 2);
    }

    public function test_beschikbaarheid_telt_ook_niet_definitieve_afspraken_mee(): void
    {
        $employee = $this->admin();
        $date = $this->weekday();
        $this->appointment($this->client(), 'Bevestigd door klant', $date, '11:00 - 12:00', [$employee]);

        $this->actingAs($this->client())
            ->postJson(route('client.appointments.check'), [
                'employee_id' => $employee->id, 'date' => $date, 'time_slot' => '11:00 - 12:00',
            ])
            ->assertOk()
            ->assertJson(['status' => 'conflict']);
    }

    public function test_bevestigen_van_inmiddels_bezet_moment_wordt_geweigerd(): void
    {
        $employee = $this->admin();
        $date = $this->weekday();
        $client = $this->client();
        [$appointment, $option] = $this->proposal($client, $employee, $date);

        // Intussen is 14:00 - 15:00 al door iemand anders bezet
        $this->appointment($this->client(), 'Bevestigd', $date, '14:00 - 15:00', [$employee]);

        $this->actingAs($client)
            ->postJson(route('client.appointments.confirmSlot', $appointment), ['option_id' => $option->id])
            ->assertStatus(409); // conflict: het moment is inmiddels bezet

        $this->assertSame('Voorstel', $appointment->fresh()->status);
    }

    public function test_admin_kan_geen_dubbele_afspraak_goedkeuren(): void
    {
        Mail::fake();
        $employee = $this->admin();
        $date = $this->weekday();
        $this->appointment($this->client(), 'Bevestigd', $date, '09:00 - 10:00', [$employee]);
        $pending = $this->appointment($this->client(), 'In afwachting', $date, '09:00 - 10:00', [$employee]);

        $this->actingAs($this->admin())
            ->patch(route('admin.appointments.approve', $pending))
            ->assertSessionHas('error');

        $this->assertSame('In afwachting', $pending->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_voorstel_op_een_bezet_moment_reserveert_niets(): void
    {
        // ADR-011, besluit 6: de admin ziet bij het plannen een waarschuwing, maar een voorstel
        // wordt niet geweigerd. Bevestigen van een inmiddels bezet moment wel (409, zie
        // test_bevestigen_van_inmiddels_bezet_moment_wordt_geweigerd).
        $employee = $this->admin();
        $date = $this->weekday();
        $client = $this->client();
        $this->appointment($this->client(), 'Bevestigd', $date, '13:00 - 14:00', [$employee]);

        $this->actingAs($this->admin())->post(route('admin.appointments.store'), [
            'client_id'      => $client->id,
            'project_id'     => $this->project($client)->id,
            'title'          => 'Voorstel',
            'type'           => 'online',
            'employees'      => [$employee->id],
            'proposal_dates' => [['date' => $date, 'time_slot' => '13:00 - 14:00']],
        ])->assertSessionHas('success');

        $this->assertDatabaseCount('appointments', 2);
    }

    // ---------- 4. Datum en tijd ----------

    public function test_weekend_wordt_geweigerd(): void
    {
        $client = $this->client();

        $this->actingAs($client)->post(route('client.appointments.store'), [
            'project_id' => $this->project($client)->id,
            'type'       => 'online',
            'title'      => 'Test',
            'date'       => now()->next(Carbon::SATURDAY)->format('Y-m-d'),
            'time_slot'  => '09:00 - 10:00',
            'employees'  => [$this->admin()->id],
        ])->assertSessionHas('error');

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_onbekend_tijdslot_wordt_geweigerd(): void
    {
        $client = $this->client();

        $this->actingAs($client)->post(route('client.appointments.store'), [
            'project_id' => $this->project($client)->id,
            'type'       => 'online',
            'title'      => 'Test',
            'date'       => $this->weekday(),
            'time_slot'  => '07:00 - 08:00',
            'employees'  => [$this->admin()->id],
        ])->assertSessionHas('error');

        $this->assertDatabaseCount('appointments', 0);
    }

    // ---------- 5. Goedkeuren en mail ----------

    public function test_voorstel_kan_niet_direct_worden_goedgekeurd(): void
    {
        [$appointment] = $this->proposal($this->client(), $this->admin(), $this->weekday());

        $this->actingAs($this->admin())
            ->patch(route('admin.appointments.approve', $appointment))
            ->assertSessionHas('error');

        $this->assertSame('Voorstel', $appointment->fresh()->status);
    }

    public function test_mail_gaat_alleen_naar_het_ingestelde_adres(): void
    {
        Mail::fake();
        config([
            'appointments.confirmation_mail.enabled' => true,
            'appointments.confirmation_mail.only_to' => 'test@example.com',
        ]);

        $client = User::factory()->create(['is_admin' => false, 'email' => 'test@example.com']);
        $appointment = $this->appointment($client, 'In afwachting', $this->weekday(), '09:00 - 10:00', [$this->admin()]);

        $this->actingAs($this->admin())->patch(route('admin.appointments.approve', $appointment));

        $this->assertSame('Bevestigd', $appointment->fresh()->status);
        Mail::assertSent(AppointmentConfirmed::class, 1);
        Mail::assertSent(AppointmentConfirmed::class, fn ($mail) => $mail->hasTo('test@example.com'));
    }
}