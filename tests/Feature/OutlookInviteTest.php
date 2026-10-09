<?php

namespace Tests\Feature;

use App\Mail\AppointmentConfirmed;
use App\Models\Appointment;
use App\Models\Project;
use App\Models\User;
use App\Services\OutlookInvite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Test de knop "Zet in Outlook": de link die Outlook opent met de uitnodiging
 * al ingevuld (klant als genodigde, Teams aan bij een online gesprek).
 */
class OutlookInviteTest extends TestCase
{
    use RefreshDatabase;

    // ---------- Hulpfuncties ----------

    private function admin(string $email): User
    {
        return User::factory()->create(['is_admin' => true, 'email' => $email]);
    }

    private function client(string $email = 'klant@example.com'): User
    {
        return User::factory()->create(['is_admin' => false, 'email' => $email]);
    }

    private function appointment(User $client, array $employees, string $type = 'online', string $status = 'Bevestigd', string $start = '2026-10-20 10:00:00', string $end = '2026-10-20 11:00:00', array $extra = []): Appointment
    {
        $project = Project::forceCreate(['user_id' => $client->id, 'name' => 'Testproject']);

        $appointment = Appointment::create(array_merge([
            'user_id'    => $client->id,
            'project_id' => $project->id,
            'title'      => 'Maandelijkse review & planning',
            'type'       => $type,
            'start_time' => $start,
            'end_time'   => $end,
            'status'     => $status,
        ], $extra));

        $appointment->attendees()->attach(collect($employees)->pluck('id')->all());

        return $appointment;
    }

    /**
     * De onderdelen van de link, uitgepakt (bijv. ['subject' => '...', 'to' => '...']).
     */
    private function parts(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $parts);

        return $parts;
    }

    // ---------- 1. De link zelf ----------

    public function test_link_opent_outlook_met_titel_en_de_klant_als_genodigde(): void
    {
        $organizer = $this->admin('stijn@example.com');
        $appointment = $this->appointment($this->client(), [$organizer]);

        $url = OutlookInvite::composeUrl($appointment, $organizer);
        $parts = $this->parts($url);

        $this->assertStringStartsWith('https://outlook.office.com/calendar/deeplink/compose?', $url);
        $this->assertSame('Maandelijkse review & planning', $parts['subject']);
        $this->assertSame('klant@example.com', $parts['to']);
    }

    public function test_tijden_staan_in_utc_in_zomer_en_winter(): void
    {
        $organizer = $this->admin('stijn@example.com');
        $client = $this->client();

        // 10:00 in Nederland is in de zomer 08:00 UTC en in de winter 09:00 UTC
        $summer = $this->parts(OutlookInvite::composeUrl($this->appointment($client, [$organizer]), $organizer));
        $winter = $this->parts(OutlookInvite::composeUrl(
            $this->appointment($client, [$organizer], 'online', 'Bevestigd', '2026-12-01 10:00:00', '2026-12-01 11:00:00'),
            $organizer
        ));

        $this->assertSame('2026-10-20T08:00:00Z', $summer['startdt']);
        $this->assertSame('2026-10-20T09:00:00Z', $summer['enddt']);
        $this->assertSame('2026-12-01T09:00:00Z', $winter['startdt']);
    }

    public function test_online_gesprek_zet_teams_aan(): void
    {
        $organizer = $this->admin('stijn@example.com');
        $parts = $this->parts(OutlookInvite::composeUrl($this->appointment($this->client(), [$organizer], 'online'), $organizer));

        $this->assertSame('true', $parts['online']);
        $this->assertArrayNotHasKey('location', $parts);
    }

    public function test_telefonisch_en_op_kantoor_krijgen_geen_teams_maar_een_locatie(): void
    {
        $organizer = $this->admin('stijn@example.com');
        $client = $this->client();

        $phone = $this->parts(OutlookInvite::composeUrl($this->appointment($client, [$organizer], 'telefoon'), $organizer));
        $office = $this->parts(OutlookInvite::composeUrl($this->appointment($client, [$organizer], 'fysiek'), $organizer));

        $this->assertArrayNotHasKey('online', $phone);
        $this->assertSame('Telefonisch', $phone['location']);
        $this->assertArrayNotHasKey('online', $office);
        $this->assertSame('Op kantoor bij GKR', $office['location']);
    }

    public function test_collega_wordt_ook_uitgenodigd_maar_de_organisator_zelf_niet(): void
    {
        $organizer = $this->admin('stijn@example.com');
        $colleague = $this->admin('owen@example.com');
        $appointment = $this->appointment($this->client(), [$organizer, $colleague]);

        $invitees = explode(',', $this->parts(OutlookInvite::composeUrl($appointment, $organizer))['to']);

        $this->assertEqualsCanonicalizing(['klant@example.com', 'owen@example.com'], $invitees);
    }

    public function test_spaties_worden_geen_plustekens_in_de_titel(): void
    {
        $organizer = $this->admin('stijn@example.com');
        $url = OutlookInvite::composeUrl($this->appointment($this->client(), [$organizer]), $organizer);

        $this->assertStringContainsString('subject=Maandelijkse%20review', $url);
        $this->assertStringNotContainsString('+', $url);
    }

    public function test_lange_opmerking_wordt_ingekort(): void
    {
        $organizer = $this->admin('stijn@example.com');
        $appointment = $this->appointment($this->client(), [$organizer], 'online', 'Bevestigd', '2026-10-20 10:00:00', '2026-10-20 11:00:00', [
            'description' => str_repeat('Lange opmerking. ', 100),
        ]);

        $body = $this->parts(OutlookInvite::composeUrl($appointment, $organizer))['body'];

        $this->assertLessThanOrEqual(610, mb_strlen($body));
    }

    // ---------- 2. Waar de knop verschijnt ----------

    public function test_na_goedkeuren_staat_de_outlook_link_klaar(): void
    {
        Mail::fake();
        $organizer = $this->admin('stijn@example.com');
        $pending = $this->appointment($this->client(), [$organizer], 'online', 'In afwachting', now()->addWeek()->format('Y-m-d') . ' 10:00:00', now()->addWeek()->format('Y-m-d') . ' 11:00:00');

        $this->actingAs($organizer)
            ->patch(route('admin.appointments.approve', $pending))
            ->assertSessionHas('outlook_url', fn ($url) => str_starts_with($url, 'https://outlook.office.com/calendar/deeplink/compose?')
                && str_contains($url, 'klant%40example.com'));

        $this->assertSame('Bevestigd', $pending->fresh()->status);
    }

    public function test_adminkalender_heeft_de_link_alleen_bij_een_definitieve_afspraak(): void
    {
        $organizer = $this->admin('stijn@example.com');
        $client = $this->client();

        $this->appointment($client, [$organizer], 'online', 'In afwachting');
        $this->actingAs($organizer)->get(route('admin.appointments.index'))
            ->assertOk()
            ->assertDontSee('deeplink');

        $this->appointment($client, [$organizer], 'online', 'Bevestigd', '2026-10-21 10:00:00', '2026-10-21 11:00:00');
        $this->actingAs($organizer)->get(route('admin.appointments.index'))
            ->assertOk()
            ->assertSee('deeplink')
            ->assertSee('Zet in Outlook');
    }

    public function test_klant_krijgt_de_link_met_e_mailadressen_nooit_te_zien(): void
    {
        $organizer = $this->admin('stijn@example.com');
        $colleague = $this->admin('owen@example.com');
        $client = $this->client();
        $this->appointment($client, [$organizer, $colleague]);

        $this->actingAs($client)->get(route('client.appointments.index'))
            ->assertOk()
            ->assertDontSee('deeplink')
            ->assertDontSee('owen@example.com')
            ->assertDontSee('stijn@example.com');
    }

    // ---------- 3. De bevestigingsmail ----------

    public function test_mail_kondigt_de_teams_uitnodiging_alleen_aan_bij_een_online_gesprek(): void
    {
        $organizer = $this->admin('stijn@example.com');
        $client = $this->client();

        $online = (new AppointmentConfirmed($this->appointment($client, [$organizer], 'online')))->render();
        $phone = (new AppointmentConfirmed($this->appointment($client, [$organizer], 'telefoon')))->render();

        $this->assertStringContainsString('Teams-uitnodiging', $online);
        $this->assertStringNotContainsString('Teams-uitnodiging', $phone);
    }
}