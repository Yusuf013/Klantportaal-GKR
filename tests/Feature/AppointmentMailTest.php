<?php

namespace Tests\Feature;

use App\Mail\AppointmentConfirmed;
use App\Models\Appointment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Test de bevestigingsmail van een afspraak: de klant en de medewerker krijgen
 * elk hun eigen versie. Er wordt niets echt verstuurd.
 */
class AppointmentMailTest extends TestCase
{
    use RefreshDatabase;

    // ---------- Hulpfuncties ----------

    private function client(): User
    {
        return User::factory()->create(['is_admin' => false, 'name' => 'Karin Klant', 'email' => 'karin@example.com']);
    }

    private function employee(string $name = 'Stijn Medewerker', string $email = 'stijn@example.com'): User
    {
        return User::factory()->create(['is_admin' => true, 'name' => $name, 'email' => $email]);
    }

    private function appointment(User $client, array $employees, string $type = 'online', string $status = 'Bevestigd'): Appointment
    {
        $project = Project::forceCreate(['user_id' => $client->id, 'name' => 'Testproject']);

        $appointment = Appointment::create([
            'user_id'    => $client->id,
            'project_id' => $project->id,
            'title'      => 'Maandelijkse review',
            'type'       => $type,
            'start_time' => '2026-10-20 10:00:00',
            'end_time'   => '2026-10-20 11:00:00',
            'status'     => $status,
        ]);

        $appointment->attendees()->attach(collect($employees)->pluck('id')->all());

        return $appointment->fresh(['client', 'attendees']);
    }

    // ---------- 1. De versie voor de klant ----------

    public function test_klant_krijgt_de_mail_op_eigen_naam_met_agendaknoppen(): void
    {
        $client = $this->client();
        $appointment = $this->appointment($client, [$this->employee()]);

        $mail = new AppointmentConfirmed($appointment, $client);
        $html = $mail->render();

        $this->assertSame('Bevestiging: Je gesprek is definitief ingepland', $mail->envelope()->subject);
        $this->assertStringContainsString('Beste Karin Klant,', $html);
        $this->assertStringContainsString('Apple Agenda', $html);
        $this->assertStringContainsString('ontvang je apart een Teams-uitnodiging', $html);
        $this->assertStringNotContainsString('Zet in Outlook', $html);
    }

    public function test_klantmail_bevat_nooit_de_outlook_link_of_adressen_van_medewerkers(): void
    {
        $client = $this->client();
        $appointment = $this->appointment($client, [$this->employee(), $this->employee('Owen Collega', 'owen@example.com')]);

        $html = (new AppointmentConfirmed($appointment, $client))->render();

        // De link achter "Zet in Outlook" bevat e-mailadressen en is alleen voor GKR
        $this->assertStringNotContainsString('outlook.office.com', $html);
        $this->assertStringNotContainsString('stijn@example.com', $html);
        $this->assertStringNotContainsString('owen@example.com', $html);
    }

    public function test_zonder_ontvanger_krijg_je_de_klantversie(): void
    {
        $appointment = $this->appointment($this->client(), [$this->employee()]);

        $html = (new AppointmentConfirmed($appointment))->render();

        $this->assertStringContainsString('Beste Karin Klant,', $html);
        $this->assertStringNotContainsString('Zet in Outlook', $html);
    }

    // ---------- 2. De versie voor de medewerker ----------

    public function test_medewerker_krijgt_eigen_aanhef_de_klantnaam_en_de_outlook_knop(): void
    {
        $employee = $this->employee();
        $appointment = $this->appointment($this->client(), [$employee]);

        $mail = new AppointmentConfirmed($appointment, $employee);
        $html = $mail->render();

        $this->assertSame('Bevestigd: gesprek met Karin Klant', $mail->envelope()->subject);
        $this->assertStringContainsString('Beste Stijn Medewerker,', $html);
        $this->assertStringNotContainsString('Beste Karin Klant', $html);
        $this->assertStringContainsString('Het gesprek met Karin Klant is definitief', $html);
        $this->assertStringContainsString('Zet in Outlook', $html);
        $this->assertStringContainsString('https://outlook.office.com/calendar/deeplink/compose', $html);
        // De knoppen voor de klant horen er niet in
        $this->assertStringNotContainsString('Apple Agenda', $html);
        $this->assertStringNotContainsString('ontvang je apart', $html);
    }

    public function test_outlook_knop_in_de_mail_nodigt_de_klant_uit_en_niet_de_medewerker_zelf(): void
    {
        $employee = $this->employee();
        $appointment = $this->appointment($this->client(), [$employee]);

        $html = html_entity_decode((new AppointmentConfirmed($appointment, $employee))->render());

        $this->assertStringContainsString('to=karin%40example.com', $html);
        $this->assertStringNotContainsString('stijn%40example.com', $html);
        // Online gesprek: de Teams-schakelaar staat aan
        $this->assertStringContainsString('online=true', $html);
    }

    public function test_medewerker_leest_alleen_bij_een_online_gesprek_over_teams(): void
    {
        $employee = $this->employee();
        $client = $this->client();

        $online = (new AppointmentConfirmed($this->appointment($client, [$employee], 'online'), $employee))->render();
        $phone = (new AppointmentConfirmed($this->appointment($client, [$employee], 'telefoon'), $employee))->render();

        $this->assertStringContainsString('Teams-uitnodiging', $online);
        $this->assertStringContainsString('met een Teams-link', $online);
        $this->assertStringNotContainsString('Teams', $phone);
    }

    public function test_bij_meer_medewerkers_staat_erbij_dat_een_van_hen_de_uitnodiging_verstuurt(): void
    {
        $stijn = $this->employee();
        $owen = $this->employee('Owen Collega', 'owen@example.com');
        $client = $this->client();

        $together = (new AppointmentConfirmed($this->appointment($client, [$stijn, $owen]), $stijn))->render();
        $alone = (new AppointmentConfirmed($this->appointment($client, [$stijn]), $stijn))->render();

        $this->assertStringContainsString('Spreek af wie de uitnodiging verstuurt', $together);
        $this->assertStringNotContainsString('Spreek af wie de uitnodiging verstuurt', $alone);
    }

    public function test_klant_die_later_beheerder_werd_krijgt_toch_de_klantversie(): void
    {
        $client = $this->client();
        $appointment = $this->appointment($client, [$this->employee()]);

        // Het account van de klant krijgt later beheerrechten
        $client->is_admin = true;
        $client->save();

        $html = (new AppointmentConfirmed($appointment->fresh(['client', 'attendees']), $client->fresh()))->render();

        $this->assertStringContainsString('Apple Agenda', $html);
        $this->assertStringNotContainsString('outlook.office.com', $html);
    }

    // ---------- 3. Versturen bij het goedkeuren ----------

    public function test_bij_goedkeuren_krijgen_klant_en_medewerker_elk_hun_eigen_versie(): void
    {
        Mail::fake();
        config(['services.appointments.mail_everyone' => true]);

        $client = $this->client();
        $employee = $this->employee();
        $appointment = $this->appointment($client, [$employee], 'online', 'In afwachting');

        $this->actingAs($employee)->patch(route('admin.appointments.approve', $appointment));

        $this->assertSame('Bevestigd', $appointment->fresh()->status);
        Mail::assertSent(AppointmentConfirmed::class, 2);

        Mail::assertSent(AppointmentConfirmed::class, fn ($mail) => $mail->hasTo('karin@example.com')
            && str_contains($mail->render(), 'Beste Karin Klant,')
            && ! str_contains($mail->render(), 'Zet in Outlook'));

        Mail::assertSent(AppointmentConfirmed::class, fn ($mail) => $mail->hasTo('stijn@example.com')
            && str_contains($mail->render(), 'Beste Stijn Medewerker,')
            && str_contains($mail->render(), 'Zet in Outlook'));
    }

    public function test_met_alleen_een_testadres_krijgt_die_ontvanger_zijn_eigen_versie(): void
    {
        Mail::fake();
        config([
            'services.appointments.mail_everyone'       => false,
            'services.appointments.mail_test_recipient' => 'STIJN@example.com', // hoofdletters maken niet uit
        ]);

        $employee = $this->employee();
        $appointment = $this->appointment($this->client(), [$employee], 'online', 'In afwachting');

        $this->actingAs($employee)->patch(route('admin.appointments.approve', $appointment));

        Mail::assertSent(AppointmentConfirmed::class, 1);
        Mail::assertSent(AppointmentConfirmed::class, fn ($mail) => $mail->hasTo('stijn@example.com')
            && str_contains($mail->render(), 'Beste Stijn Medewerker,'));
    }
}