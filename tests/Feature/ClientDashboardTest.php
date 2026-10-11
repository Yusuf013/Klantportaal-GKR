<?php

namespace Tests\Feature;

use App\Models\AdAccount;
use App\Models\Appointment;
use App\Models\Document;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use App\Services\GoogleAccessToken;
use App\Services\GoogleAdsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Test het dashboard van de klant: wat er op de klant wacht, de volgende afspraak,
 * de projecten en de advertentiecijfers in het kort.
 *
 * Vaste datum: het is maandag 12 oktober 2026, 14:00 uur.
 */
class ClientDashboardTest extends TestCase
{
    use RefreshDatabase;

    // ---------- Hulpfuncties ----------

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-12 14:00:00', 'Europe/Amsterdam'));
        Cache::flush();
    }

    private function client(string $name = 'Karin Klant'): User
    {
        return User::factory()->create(['is_admin' => false, 'name' => $name]);
    }

    private function admin(string $name = 'Stijn Medewerker'): User
    {
        return User::factory()->create(['is_admin' => true, 'name' => $name]);
    }

    private function project(User $client, string $name = 'Nieuwe website', array $extra = []): Project
    {
        return Project::forceCreate(['user_id' => $client->id, 'name' => $name] + $extra);
    }

    private function appointment(User $client, Project $project, string $status, string $start, string $title = 'Maandelijkse review', string $type = 'online'): Appointment
    {
        return Appointment::create([
            'user_id'    => $client->id,
            'project_id' => $project->id,
            'title'      => $title,
            'type'       => $type,
            'start_time' => $start,
            'end_time'   => Carbon::parse($start)->addHour()->format('Y-m-d H:i:s'),
            'status'     => $status,
        ]);
    }

    private function document(Project $project, string $name, string $status = 'Verzonden'): Document
    {
        return Document::create(['project_id' => $project->id, 'name' => $name, 'file_path' => 'documents/test.pdf', 'status' => $status]);
    }

    private function dashboard(User $user)
    {
        return $this->actingAs($user)->get(route('dashboard'));
    }

    // ---------- 1. Toegang en begroeting ----------

    public function test_gast_wordt_naar_login_gestuurd(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_klant_ziet_een_begroeting_met_de_voornaam(): void
    {
        $this->dashboard($this->client('Karin van den Klant'))
            ->assertOk()
            ->assertSee('Goedemiddag, Karin')
            // De oude Engelse standaardtekst van Laravel is weg
            ->assertDontSee('logged in');
    }

    public function test_begroeting_past_bij_het_moment_van_de_dag(): void
    {
        $client = $this->client();

        $this->travelTo(Carbon::parse('2026-10-12 08:30:00', 'Europe/Amsterdam'));
        $this->dashboard($client)->assertSee('Goedemorgen, Karin');

        $this->travelTo(Carbon::parse('2026-10-12 20:30:00', 'Europe/Amsterdam'));
        $this->dashboard($client)->assertSee('Goedenavond, Karin');
    }

    public function test_medewerker_wordt_doorgestuurd_naar_het_dashboard_van_gkr(): void
    {
        $this->dashboard($this->admin())->assertRedirect(route('admin.dashboard'));
    }

    // ---------- 2. Wat er op de klant wacht ----------

    public function test_staat_er_niets_open_dan_zegt_de_pagina_dat(): void
    {
        $this->dashboard($this->client())
            ->assertOk()
            ->assertSee('Je bent helemaal bij')
            ->assertSee('data-open="0"', false)
            ->assertDontSee('data-action=', false);
    }

    public function test_afspraakvoorstel_van_gkr_staat_in_de_lijst(): void
    {
        $client = $this->client();
        $this->appointment($client, $this->project($client), 'Voorstel', '2026-10-20 10:00:00', 'Kick-off campagne');

        $this->dashboard($client)
            ->assertOk()
            ->assertSee('data-action="proposals" data-count="1"', false)
            ->assertSee('GKR heeft een afspraak voorgesteld: "Kick-off campagne"')
            ->assertSee('Moment kiezen')
            ->assertSee('Er staat 1 ding voor je klaar.');
    }

    public function test_een_document_dat_op_akkoord_wacht_linkt_naar_dat_document(): void
    {
        $client = $this->client();
        $project = $this->project($client);
        $document = $this->document($project, 'Offerte najaar');
        // Al goedgekeurd: telt niet mee
        $this->document($project, 'Oude offerte', 'Akkoord');

        $this->dashboard($client)
            ->assertOk()
            ->assertSee('data-action="documents" data-count="1"', false)
            ->assertSee('Het document "Offerte najaar" wacht op je akkoord.')
            ->assertSee('href="' . route('documents.show', $document) . '"', false)
            ->assertDontSee('Oude offerte');
    }

    public function test_meerdere_documenten_linken_naar_het_overzicht(): void
    {
        $client = $this->client();
        $project = $this->project($client);
        $this->document($project, 'Offerte');
        $this->document($project, 'Planning');

        $this->dashboard($client)
            ->assertSee('data-action="documents" data-count="2"', false)
            ->assertSee('2 documenten wachten op je akkoord.')
            ->assertSee('href="' . route('documents.index') . '"', false);
    }

    public function test_ongelezen_berichten_van_gkr_staan_in_de_lijst(): void
    {
        $admin = $this->admin();
        $client = $this->client();

        Message::create(['client_id' => $client->id, 'sender_id' => $admin->id, 'receiver_id' => $client->id, 'body' => 'Eerste antwoord']);
        Message::create(['client_id' => $client->id, 'sender_id' => $admin->id, 'receiver_id' => $client->id, 'body' => 'Tweede antwoord']);
        // Een eigen bericht van de klant telt niet mee
        Message::create(['client_id' => $client->id, 'sender_id' => $client->id, 'body' => 'Mijn vraag']);

        $this->dashboard($client)
            ->assertSee('data-action="messages" data-count="2"', false)
            ->assertSee('Je hebt 2 nieuwe berichten van GKR.')
            // De tekst van een bericht staat niet op het dashboard
            ->assertDontSee('Eerste antwoord');
    }

    public function test_alle_drie_tegelijk_geeft_drie_regels(): void
    {
        $admin = $this->admin();
        $client = $this->client();
        $project = $this->project($client);

        $this->appointment($client, $project, 'Voorstel', '2026-10-20 10:00:00');
        $this->document($project, 'Offerte');
        Message::create(['client_id' => $client->id, 'sender_id' => $admin->id, 'receiver_id' => $client->id, 'body' => 'Antwoord']);

        $this->dashboard($client)
            ->assertSee('data-open="3"', false)
            ->assertSee('Er staan 3 dingen voor je klaar.')
            ->assertSee('Je hebt 1 nieuw bericht van GKR.');
    }

    public function test_gegevens_van_een_andere_klant_tellen_nooit_mee(): void
    {
        $admin = $this->admin();
        $karin = $this->client('Karin Klant');
        $bram = $this->client('Bram Bakker');

        // Alles hieronder is van Bram
        $project = $this->project($bram, 'Geheim project van Bram');
        $this->appointment($bram, $project, 'Voorstel', '2026-10-20 10:00:00', 'Voorstel voor Bram');
        $this->appointment($bram, $project, 'Bevestigd', '2026-10-21 10:00:00', 'Afspraak van Bram');
        $this->document($project, 'Offerte voor Bram');
        Message::create(['client_id' => $bram->id, 'sender_id' => $admin->id, 'receiver_id' => $bram->id, 'body' => 'Bericht voor Bram']);

        $this->dashboard($karin)
            ->assertOk()
            ->assertSee('data-open="0"', false)
            ->assertDontSee('Geheim project van Bram')
            ->assertDontSee('Voorstel voor Bram')
            ->assertDontSee('Afspraak van Bram')
            ->assertDontSee('Offerte voor Bram')
            ->assertSee('Er staat nog geen afspraak gepland.');
    }

    // ---------- 3. Volgende afspraak ----------

    public function test_toont_de_eerstvolgende_definitieve_afspraak(): void
    {
        $client = $this->client();
        $project = $this->project($client);

        // Deze tellen niet als "volgende afspraak":
        $this->appointment($client, $project, 'Bevestigd', '2026-10-05 10:00:00', 'Afspraak van vorige week');   // voorbij
        $this->appointment($client, $project, 'Geannuleerd', '2026-10-13 10:00:00', 'Geannuleerde afspraak');
        $this->appointment($client, $project, 'In afwachting', '2026-10-14 10:00:00', 'Aanvraag zonder bevestiging');

        // Deze wel, en de vroegste van de twee wint
        $next = $this->appointment($client, $project, 'Bevestigd', '2026-10-20 10:00:00', 'Kick-off campagne');
        $next->attendees()->attach($this->admin('Stijn Medewerker')->id);
        $this->appointment($client, $project, 'Bevestigd', '2026-10-27 13:00:00', 'Latere afspraak');

        $this->dashboard($client)
            ->assertOk()
            ->assertSee('Kick-off campagne')
            ->assertSee('Dinsdag 20 oktober, 10:00 - 11:00 uur')
            ->assertSee('Online')
            ->assertSee('met Stijn Medewerker')
            ->assertSee('Naar mijn agenda')
            ->assertDontSee('Afspraak van vorige week')
            ->assertDontSee('Geannuleerde afspraak')
            ->assertDontSee('Latere afspraak')
            // De aanvraag zonder bevestiging staat als melding onder de afspraak
            ->assertSee('data-waiting-for-gkr="1"', false)
            ->assertSee('1 aanvraag wacht op bevestiging van GKR.');
    }

    public function test_afspraak_van_morgen_heet_morgen(): void
    {
        $client = $this->client();
        $this->appointment($client, $this->project($client), 'Bevestigd', '2026-10-13 09:00:00', 'Korte afstemming', 'telefoon');

        $this->dashboard($client)
            ->assertSee('Morgen, 09:00 - 10:00 uur')
            ->assertSee('Telefonisch');
    }

    public function test_zonder_afspraak_is_er_een_knop_om_er_een_te_maken(): void
    {
        $this->dashboard($this->client())
            ->assertSee('Er staat nog geen afspraak gepland.')
            ->assertSee('Afspraak maken')
            ->assertDontSee('data-waiting-for-gkr', false);
    }

    // ---------- 4. Projecten ----------

    public function test_project_toont_voortgang_en_dagen_tot_de_deadline(): void
    {
        $client = $this->client();
        $project = $this->project($client, 'Nieuwe website', ['status' => 'Development', 'progress' => 65, 'deadline' => '2026-10-24']);

        $this->dashboard($client)
            ->assertOk()
            ->assertSee('Nieuwe website')
            ->assertSee('Development')
            ->assertSee('65%')
            ->assertSee('Deadline over 12 dagen (24-10-2026)')
            ->assertSee('href="' . route('projects.show', $project) . '"', false);
    }

    public function test_dagen_tellen_klopt_ook_over_de_wisseling_naar_wintertijd_heen(): void
    {
        // In de nacht van 24 op 25 oktober 2026 gaat de klok een uur terug
        $client = $this->client();
        $this->project($client, 'Nieuwe website', ['deadline' => '2026-10-26']);

        $this->dashboard($client)->assertSee('Deadline over 14 dagen (26-10-2026)');
    }

    public function test_project_zonder_deadline_toont_geen_verzonnen_datum(): void
    {
        $client = $this->client();
        $this->project($client, 'Doorlopend onderhoud');

        $this->dashboard($client)
            ->assertSee('Geen deadline')
            // Voorheen stond hier de datum van vandaag als deadline
            ->assertDontSee('12-10-2026');
    }

    public function test_deadline_vandaag_morgen_en_voorbij(): void
    {
        $client = $this->client();
        $this->project($client, 'Project A', ['deadline' => '2026-10-12']);
        $this->project($client, 'Project B', ['deadline' => '2026-10-13']);
        $this->project($client, 'Project C', ['deadline' => '2026-10-01']);

        $this->dashboard($client)
            ->assertSee('Deadline vandaag')
            ->assertSee('Deadline morgen')
            ->assertSee('Deadline 01-10-2026');
    }

    public function test_afgerond_project_staat_op_honderd_procent_en_onderaan(): void
    {
        $client = $this->client();
        $this->project($client, 'Huisstijl en logo', ['status' => 'Live', 'progress' => 80, 'deadline' => '2026-09-01']);
        $this->project($client, 'Campagne najaar', ['status' => 'Strategie', 'progress' => 20, 'deadline' => '2026-11-15']);
        $this->project($client, 'Nieuwe website', ['status' => 'Development', 'progress' => 65, 'deadline' => '2026-10-24']);

        $this->dashboard($client)
            ->assertOk()
            // Lopende projecten eerst (vroegste deadline bovenaan), het afgeronde project onderaan
            ->assertSeeInOrder(['Nieuwe website', 'Campagne najaar', 'Huisstijl en logo'])
            ->assertSee('Deadline was 01-09-2026')
            ->assertSee('100%');
    }

    public function test_voortgang_blijft_altijd_tussen_nul_en_honderd(): void
    {
        $client = $this->client();
        $this->project($client, 'Te veel', ['progress' => 150]);
        $this->project($client, 'Te weinig', ['progress' => -20]);

        $this->dashboard($client)
            ->assertSee('width: 100%', false)
            ->assertSee('width: 0%', false)
            ->assertDontSee('150%')
            ->assertDontSee('-20%');
    }

    public function test_zonder_projecten_staat_er_een_uitleg(): void
    {
        $this->dashboard($this->client())->assertSee('Er zijn nog geen projecten aan je account gekoppeld');
    }

    public function test_projectnaam_met_code_wordt_onschadelijk_gemaakt(): void
    {
        $client = $this->client();
        $this->project($client, '<script>alert(1)</script>');

        $this->dashboard($client)
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    // ---------- 5. Advertenties in het kort ----------

    public function test_zonder_gekoppeld_account_is_er_geen_blok_met_advertenties(): void
    {
        $this->dashboard($this->client())->assertDontSee('Advertenties in het kort');
    }

    public function test_met_nepdata_toont_het_blok_testgegevens_zonder_iets_op_te_halen(): void
    {
        // Standaard in de tests: Google Ads en Meta staan op nepdata
        Http::fake();

        $client = $this->client();
        $client->adAccounts()->create(['platform' => AdAccount::PLATFORM_GOOGLE_ADS, 'account_id' => '1111111111']);

        $this->dashboard($client)
            ->assertOk()
            ->assertSee('Advertenties in het kort')
            ->assertSee('data-ads="Google Ads"', false)
            ->assertSee('Testgegevens')
            ->assertSee('Klikken')
            // Alleen het gekoppelde platform staat erbij
            ->assertDontSee('data-ads="Meta Ads"', false);

        Http::assertNothingSent();
    }

    public function test_beide_platforms_staan_erbij_als_beide_gekoppeld_zijn(): void
    {
        Http::fake();

        $client = $this->client();
        $client->adAccounts()->create(['platform' => AdAccount::PLATFORM_GOOGLE_ADS, 'account_id' => '1111111111']);
        $client->adAccounts()->create(['platform' => AdAccount::PLATFORM_META, 'account_id' => '123456789']);

        $this->dashboard($client)
            ->assertSee('data-ads="Google Ads"', false)
            ->assertSee('data-ads="Meta Ads"', false);

        Http::assertNothingSent();
    }

    public function test_het_dashboard_wacht_nooit_op_google_als_er_geen_rapport_klaarligt(): void
    {
        // Echte cijfers aan, maar er ligt nog geen rapport klaar
        config(['services.google_ads.fake' => false]);
        Http::fake();

        $client = $this->client();
        $client->adAccounts()->create(['platform' => AdAccount::PLATFORM_GOOGLE_ADS, 'account_id' => '1111111111']);

        $this->dashboard($client)
            ->assertOk()
            ->assertSee('data-ads="Google Ads"', false)
            ->assertSee('De cijfers worden bijgewerkt.')
            ->assertDontSee('Klikken');

        // Geen enkel verzoek naar Google: de pagina laadt direct
        Http::assertNothingSent();
    }

    public function test_ligt_er_een_rapport_klaar_dan_staan_de_cijfers_op_het_dashboard(): void
    {
        config([
            'services.google_ads.fake'              => false,
            'services.google_ads.api_version'       => 'v25',
            'services.google_ads.login_customer_id' => null,
        ]);

        $this->mock(GoogleAccessToken::class, function ($mock) {
            $mock->shouldReceive('forScope')->andReturn('test-token');
        });

        // Google geeft één rij op 10 oktober: 1.234 klikken, 12,5 conversies, € 842,50
        Http::fake(fn () => Http::response(['results' => [[
            'campaign' => ['id' => '111', 'name' => 'Zoeken - Merknaam'],
            'metrics'  => ['impressions' => '50000', 'clicks' => '1234', 'conversions' => 12.5, 'costMicros' => '842500000'],
            'segments' => ['date' => '2026-10-10'],
        ]]]));

        $client = $this->client();
        $account = $client->adAccounts()->create(['platform' => AdAccount::PLATFORM_GOOGLE_ADS, 'account_id' => '1111111111']);

        // De server heeft het rapport op de achtergrond klaargezet (wat marketing:warm doet)
        app(GoogleAdsService::class)->refresh($account, 28);
        Http::assertSentCount(2);

        $this->dashboard($client)
            ->assertOk()
            ->assertSee('1.234')
            ->assertSee('12,5')
            ->assertSee('€ 842,50')
            ->assertDontSee('Testgegevens')
            ->assertDontSee('De cijfers worden bijgewerkt.');

        // Het dashboard zelf heeft niets meer opgehaald
        Http::assertSentCount(2);
    }
}