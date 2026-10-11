<?php

namespace Tests\Feature;

use App\Models\AdAccount;
use App\Models\Appointment;
use App\Models\Comment;
use App\Models\Document;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use App\Services\AppointmentAvailability;
use App\Services\GoogleAccessToken;
use App\Services\GoogleAdsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Test het dashboard van GKR: wat er op GKR wacht, de afspraken van vandaag, deadlines,
 * koppelingen die niet werken en de projectentabel.
 *
 * Vaste datum: het is maandag 12 oktober 2026, 14:00 uur.
 */
class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    // ---------- Hulpfuncties ----------

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-12 14:00:00', 'Europe/Amsterdam'));
        Cache::flush();
    }

    private function admin(string $name = 'Stijn Medewerker'): User
    {
        return User::factory()->create(['is_admin' => true, 'name' => $name]);
    }

    private function client(string $name = 'Karin Klant'): User
    {
        return User::factory()->create(['is_admin' => false, 'name' => $name]);
    }

    private function project(User $client, string $name = 'Nieuwe website', array $extra = []): Project
    {
        return Project::forceCreate(['user_id' => $client->id, 'name' => $name] + $extra);
    }

    private function appointment(User $client, Project $project, string $status, string $start, string $title = 'Maandelijkse review'): Appointment
    {
        return Appointment::create([
            'user_id'    => $client->id,
            'project_id' => $project->id,
            'title'      => $title,
            'type'       => 'online',
            'start_time' => $start,
            'end_time'   => Carbon::parse($start)->addHour()->format('Y-m-d H:i:s'),
            'status'     => $status,
        ]);
    }

    private function document(Project $project, string $name = 'Offerte', string $status = 'Verzonden'): Document
    {
        return Document::create(['project_id' => $project->id, 'name' => $name, 'file_path' => 'documents/test.pdf', 'status' => $status]);
    }

    private function comment(Document $document, User $author, string $content = 'Graag het logo groter'): Comment
    {
        return Comment::create(['document_id' => $document->id, 'user_id' => $author->id, 'content' => $content, 'is_resolved' => false]);
    }

    private function messageFromClient(User $client, string $body = 'Vraag'): Message
    {
        return Message::create(['client_id' => $client->id, 'sender_id' => $client->id, 'body' => $body]);
    }

    private function dashboard(User $user)
    {
        return $this->actingAs($user)->get(route('admin.dashboard'));
    }

    // ---------- 1. Toegang en begroeting ----------

    public function test_gast_wordt_naar_login_gestuurd(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_klant_komt_niet_op_het_dashboard_van_gkr(): void
    {
        $this->dashboard($this->client())->assertRedirect('/dashboard');
    }

    public function test_medewerker_ziet_begroeting_en_aantallen(): void
    {
        $karin = $this->client('Karin Klant');
        $bram = $this->client('Bram Bakker');
        $this->project($karin, 'Nieuwe website', ['status' => 'Development']);
        $this->project($bram, 'Huisstijl', ['status' => 'Design']);
        $this->project($bram, 'Oude site', ['status' => 'Live']);

        $this->dashboard($this->admin('Stijn Medewerker'))
            ->assertOk()
            ->assertSee('Goedemiddag, Stijn')
            ->assertSee('2 klanten, 2 lopende projecten');
    }

    public function test_wacht_er_niets_dan_zegt_de_pagina_dat(): void
    {
        $this->dashboard($this->admin())
            ->assertOk()
            ->assertSee('Er wacht op dit moment niets op GKR')
            ->assertSee('data-open="0"', false)
            ->assertDontSee('data-action=', false);
    }

    // ---------- 2. Wat er op GKR wacht ----------

    public function test_een_afspraak_die_op_bevestiging_wacht_staat_in_de_lijst(): void
    {
        $karin = $this->client();
        $project = $this->project($karin);
        $this->appointment($karin, $project, 'In afwachting', '2026-10-20 10:00:00', 'Kick-off campagne');

        // Deze wachten niet (meer) op GKR:
        $this->appointment($karin, $project, 'In afwachting', '2026-10-05 10:00:00', 'Verlopen aanvraag');   // voorbij
        $this->appointment($karin, $project, 'Voorstel', '2026-10-21 10:00:00', 'Wacht op de klant');
        $this->appointment($karin, $project, 'Bevestigd', '2026-10-22 10:00:00', 'Al bevestigd');
        $this->appointment($karin, $project, 'Geannuleerd', '2026-10-23 10:00:00', 'Geannuleerd');

        $this->dashboard($this->admin())
            ->assertOk()
            ->assertSee('data-action="appointments" data-count="1"', false)
            ->assertSee('Karin Klant wacht op bevestiging van de afspraak "Kick-off campagne".')
            ->assertSee('href="' . route('admin.appointments.index') . '"', false);
    }

    public function test_meerdere_wachtende_afspraken_worden_geteld(): void
    {
        $karin = $this->client();
        $project = $this->project($karin);
        $this->appointment($karin, $project, 'In afwachting', '2026-10-20 10:00:00');
        $this->appointment($karin, $project, 'Bevestigd door klant', '2026-10-21 10:00:00');
        $this->appointment($karin, $project, 'Alternatief gekozen', '2026-10-22 10:00:00');

        $this->dashboard($this->admin())
            ->assertSee('data-action="appointments" data-count="3"', false)
            ->assertSee('3 afspraken wachten op bevestiging van GKR.');
    }

    public function test_een_klant_met_ongelezen_berichten_linkt_naar_dat_gesprek(): void
    {
        $admin = $this->admin();
        $karin = $this->client();
        $this->messageFromClient($karin, 'Eerste vraag');
        $this->messageFromClient($karin, 'Tweede vraag');
        // Een antwoord van GKR telt niet mee
        Message::create(['client_id' => $karin->id, 'sender_id' => $admin->id, 'receiver_id' => $karin->id, 'body' => 'Antwoord']);

        $this->dashboard($admin)
            ->assertSee('data-action="messages" data-count="1"', false)
            ->assertSee('Karin Klant heeft 2 nieuwe berichten gestuurd.')
            ->assertSee('client_id=' . $karin->id, false)
            // De tekst van een bericht staat niet op het dashboard
            ->assertDontSee('Eerste vraag');
    }

    public function test_meerdere_klanten_met_ongelezen_berichten(): void
    {
        $karin = $this->client('Karin Klant');
        $bram = $this->client('Bram Bakker');
        $cees = $this->client('Cees de Vries');
        $this->messageFromClient($karin);
        $this->messageFromClient($bram);
        // Al gelezen door GKR: telt niet mee
        $this->messageFromClient($cees)->forceFill(['read_at' => now()])->save();

        $this->dashboard($this->admin())
            ->assertSee('data-action="messages" data-count="2"', false)
            ->assertSee('2 klanten wachten op antwoord in de chat.');
    }

    public function test_reactie_van_de_klant_op_een_document_staat_in_de_lijst(): void
    {
        $karin = $this->client();
        $document = $this->document($this->project($karin), 'Concept ontwerp');
        $this->comment($document, $karin);

        $this->dashboard($this->admin())
            ->assertSee('data-action="documents" data-count="1"', false)
            ->assertSee('Karin Klant heeft gereageerd op het document "Concept ontwerp".')
            ->assertSee('href="' . route('admin.documents.show', $document) . '"', false)
            // De tekst van de reactie zelf staat niet op het dashboard
            ->assertDontSee('Graag het logo groter');
    }

    public function test_heeft_gkr_als_laatste_gereageerd_dan_wacht_het_document_niet_meer(): void
    {
        $admin = $this->admin();
        $karin = $this->client();
        $document = $this->document($this->project($karin));
        $this->comment($document, $karin, 'Vraag van de klant');
        $this->comment($document, $admin, 'Antwoord van GKR');

        $this->dashboard($admin)->assertDontSee('data-action="documents"', false);
    }

    public function test_goedgekeurd_document_wacht_niet_meer_op_gkr(): void
    {
        $karin = $this->client();
        $document = $this->document($this->project($karin), 'Offerte', 'Akkoord');
        $this->comment($document, $karin, 'Ziet er goed uit');

        $this->dashboard($this->admin())->assertDontSee('data-action="documents"', false);
    }

    public function test_bij_veel_documenten_krijgen_er_drie_een_eigen_regel(): void
    {
        $karin = $this->client();
        $project = $this->project($karin);

        foreach (['Document A', 'Document B', 'Document C', 'Document D', 'Document E'] as $name) {
            $this->comment($this->document($project, $name), $karin);
        }

        $response = $this->dashboard($this->admin())->assertOk();

        $this->assertSame(3, substr_count($response->getContent(), 'data-action="documents" '));
        $response
            ->assertSee('data-action="documents-more" data-count="2"', false)
            ->assertSee('En nog 2 documenten met een reactie van de klant.')
            // De nieuwste reactie staat bovenaan
            ->assertSeeInOrder(['Document E', 'Document D', 'Document C']);
    }

    // ---------- 3. Afspraken vandaag ----------

    public function test_toont_de_definitieve_afspraken_van_vandaag_op_volgorde(): void
    {
        $karin = $this->client('Karin Klant');
        $project = $this->project($karin);

        $this->appointment($karin, $project, 'Bevestigd', '2026-10-12 16:00:00', 'Middagafspraak');
        $morning = $this->appointment($karin, $project, 'Bevestigd', '2026-10-12 10:00:00', 'Ochtendafspraak');
        $morning->attendees()->attach($this->admin('Owen Collega')->id);

        // Niet vandaag, niet definitief of geannuleerd
        $this->appointment($karin, $project, 'Bevestigd', '2026-10-13 10:00:00', 'Afspraak van morgen');
        $this->appointment($karin, $project, 'In afwachting', '2026-10-12 11:00:00', 'Nog niet bevestigd');
        $this->appointment($karin, $project, 'Geannuleerd', '2026-10-12 13:00:00', 'Geannuleerde afspraak');

        $response = $this->dashboard($this->admin())->assertOk();

        $this->assertSame(2, substr_count($response->getContent(), 'data-today-appointment'));
        $response
            ->assertSeeInOrder(['10:00 - 11:00', 'Ochtendafspraak', '16:00 - 17:00', 'Middagafspraak'])
            ->assertSee('Karin Klant, Online')
            ->assertSee('Met Owen Collega')
            ->assertDontSee('Afspraak van morgen')
            ->assertDontSee('Geannuleerde afspraak');
    }

    public function test_zonder_afspraken_vandaag_staat_de_eerstvolgende_erbij(): void
    {
        $karin = $this->client('Karin Klant');
        $this->appointment($karin, $this->project($karin), 'Bevestigd', '2026-10-20 10:00:00', 'Kick-off campagne');

        $this->dashboard($this->admin())
            ->assertSee('Vandaag staan er geen afspraken.')
            ->assertSee('Eerstvolgende: Dinsdag 20 oktober om 10:00 - 11:00')
            ->assertSee('met Karin Klant.');
    }

    public function test_zonder_enige_afspraak_staat_er_geen_eerstvolgende(): void
    {
        $this->dashboard($this->admin())
            ->assertSee('Vandaag staan er geen afspraken.')
            ->assertDontSee('data-upcoming-appointment', false);
    }

    // ---------- 4. Deadlines ----------

    public function test_toont_deadlines_binnen_veertien_dagen_en_te_late_projecten(): void
    {
        $karin = $this->client();

        $this->project($karin, 'Te laat project', ['status' => 'Design', 'deadline' => '2026-10-09']);
        $this->project($karin, 'Bijna klaar', ['status' => 'Development', 'deadline' => '2026-10-24']);

        // Deze horen er niet bij:
        $this->project($karin, 'Ver weg', ['status' => 'Strategie', 'deadline' => '2026-11-01']);   // over 20 dagen
        $this->project($karin, 'Al live', ['status' => 'Live', 'deadline' => '2026-10-10']);          // klaar
        $this->project($karin, 'Zonder deadline', ['status' => 'Strategie']);

        $response = $this->dashboard($this->admin())->assertOk();

        $this->assertSame(2, substr_count($response->getContent(), 'data-deadline='));
        $response
            ->assertSee('data-deadline="-3"', false)
            ->assertSee('3 dagen te laat')
            ->assertSee('data-deadline="12"', false)
            ->assertSee('Over 12 dagen')
            // Het meest dringende staat bovenaan
            ->assertSeeInOrder(['3 dagen te laat', 'Over 12 dagen'])
            ->assertDontSee('Over 20 dagen');
    }

    public function test_deadline_vandaag_morgen_en_een_dag_te_laat(): void
    {
        $karin = $this->client();
        $this->project($karin, 'Project A', ['deadline' => '2026-10-11']);
        $this->project($karin, 'Project B', ['deadline' => '2026-10-12']);
        $this->project($karin, 'Project C', ['deadline' => '2026-10-13']);
        // Precies op de grens: over 14 dagen, over de wisseling naar wintertijd heen
        $this->project($karin, 'Project D', ['deadline' => '2026-10-26']);

        $this->dashboard($this->admin())
            ->assertSee('1 dag te laat')
            ->assertSee('data-deadline="0"', false)
            ->assertSee('data-deadline="1"', false)
            ->assertSee('Over 14 dagen');
    }

    public function test_zonder_deadlines_staat_er_een_uitleg(): void
    {
        $this->dashboard($this->admin())->assertSee('Geen deadlines in de komende 14 dagen.');
    }

    // ---------- 5. Koppelingen ----------

    public function test_werkt_alles_dan_is_er_geen_blok_met_koppelingen(): void
    {
        // Standaard in de tests: Google Ads en Meta staan op nepdata
        Http::fake();

        $karin = $this->client();
        $karin->adAccounts()->create(['platform' => AdAccount::PLATFORM_GOOGLE_ADS, 'account_id' => '1111111111']);
        $karin->adAccounts()->create(['platform' => AdAccount::PLATFORM_META, 'account_id' => '123456789']);

        $this->dashboard($this->admin())
            ->assertOk()
            ->assertDontSee('Koppelingen die aandacht vragen');

        Http::assertNothingSent();
    }

    public function test_een_outlook_agenda_die_niet_gelezen_kan_worden_staat_erbij(): void
    {
        Http::fake(['outlook.office365.com/*' => Http::response('', 404)]);

        $owen = $this->admin('Owen Collega');
        $owen->outlook_ics_url = 'https://outlook.office365.com/owa/calendar/test@example.com/geheim123/calendar.ics';
        $owen->save();

        // Het portaal heeft de agenda eerder geprobeerd te lezen (zoals bij het openen van de kalender)
        app(AppointmentAvailability::class)->outlookCalendarBlocks();
        Http::assertSentCount(1);

        $this->dashboard($this->admin())
            ->assertOk()
            ->assertSee('Koppelingen die aandacht vragen')
            ->assertSee('data-connection="outlook"', false)
            ->assertSee('De Outlook-agenda van Owen Collega kan niet gelezen worden')
            // De geheime link staat nergens op de pagina
            ->assertDontSee('geheim123');

        // Het dashboard zelf heeft de agenda niet opnieuw opgehaald
        Http::assertSentCount(1);
    }

    public function test_een_klant_zonder_klaarliggende_google_ads_cijfers_staat_erbij(): void
    {
        // Echte cijfers aan, maar voor deze klant ligt geen rapport klaar
        config(['services.google_ads.fake' => false]);
        Http::fake();

        $karin = $this->client('Karin Klant');
        $karin->adAccounts()->create(['platform' => AdAccount::PLATFORM_GOOGLE_ADS, 'account_id' => '1111111111']);

        $this->dashboard($this->admin())
            ->assertOk()
            ->assertSee('data-connection="google-ads"', false)
            ->assertSee('Google Ads: de cijfers van Karin Klant liggen niet klaar.');

        // Het dashboard wacht nooit op Google
        Http::assertNothingSent();
    }

    public function test_ligt_het_rapport_klaar_dan_is_er_geen_melding(): void
    {
        config([
            'services.google_ads.fake'              => false,
            'services.google_ads.api_version'       => 'v25',
            'services.google_ads.login_customer_id' => null,
        ]);

        $this->mock(GoogleAccessToken::class, function ($mock) {
            $mock->shouldReceive('forScope')->andReturn('test-token');
        });

        Http::fake(fn () => Http::response(['results' => []]));

        $karin = $this->client();
        $account = $karin->adAccounts()->create(['platform' => AdAccount::PLATFORM_GOOGLE_ADS, 'account_id' => '1111111111']);

        // De server heeft het rapport op de achtergrond klaargezet (wat marketing:warm doet)
        app(GoogleAdsService::class)->refresh($account, 28);

        $this->dashboard($this->admin())
            ->assertOk()
            ->assertDontSee('data-connection="google-ads"', false);

        Http::assertSentCount(2);
    }

    // ---------- 6. Projectentabel en meldingen ----------

    public function test_projectentabel_heeft_een_filter_met_de_statussen_die_voorkomen(): void
    {
        $karin = $this->client();
        $this->project($karin, 'Nieuwe website', ['status' => 'Development']);
        $this->project($karin, 'Oude site', ['status' => 'Live']);

        $this->dashboard($this->admin())
            ->assertOk()
            ->assertSee('id="project-status-filter"', false)
            ->assertSee('<option value="Development">Development</option>', false)
            ->assertSee('<option value="Live">Live</option>', false)
            ->assertSee('data-status="Development"', false)
            ->assertSee('data-status="Live"', false);
    }

    public function test_bij_een_enkele_status_is_er_geen_filter(): void
    {
        $karin = $this->client();
        $this->project($karin, 'Nieuwe website', ['status' => 'Development']);
        $this->project($karin, 'Webshop', ['status' => 'Development']);

        $this->dashboard($this->admin())
            ->assertOk()
            ->assertSee('Nieuwe website')
            ->assertDontSee('id="project-status-filter"', false);
    }

    public function test_na_het_aanmaken_van_een_project_staat_de_melding_op_het_dashboard(): void
    {
        $karin = $this->client();

        // Het aanmaken stuurt door naar /dashboard, en dat stuurt een medewerker door naar het dashboard van GKR
        $this->actingAs($this->admin())->followingRedirects()
            ->post(route('admin.projects.store'), ['user_id' => $karin->id, 'name' => 'Nieuw project', 'status' => 'Strategie'])
            ->assertOk()
            ->assertSee('Project succesvol gekoppeld aan klant!')
            ->assertSee('Nieuw project')
            ->assertSee('Goedemiddag');
    }

    public function test_naam_van_een_klant_met_code_wordt_onschadelijk_gemaakt(): void
    {
        $client = $this->client('<script>alert(1)</script>');
        $this->project($client, 'Nieuwe website', ['deadline' => '2026-10-13']);
        $this->messageFromClient($client);

        $this->dashboard($this->admin())
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false);
    }
}