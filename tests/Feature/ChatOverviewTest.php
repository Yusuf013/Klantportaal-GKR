<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Test de lijst met gesprekken van de medewerker: per klant het aantal ongelezen
 * berichten, het laatste bericht en de volgorde.
 */
class ChatOverviewTest extends TestCase
{
    use RefreshDatabase;

    // ---------- Hulpfuncties ----------

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true, 'name' => 'Stijn Medewerker']);
    }

    private function client(string $name): User
    {
        return User::factory()->create(['is_admin' => false, 'name' => $name]);
    }

    private function clientSends(User $client, string $body): void
    {
        $this->actingAs($client)->post(route('chat.store'), ['body' => $body])->assertSessionHasNoErrors();
    }

    private function adminReplies(User $admin, User $client, string $body): void
    {
        $this->actingAs($admin)->post(route('chat.store'), ['receiver_id' => $client->id, 'body' => $body])->assertSessionHasNoErrors();
    }

    /**
     * Het stukje van de pagina waaraan je ziet hoeveel ongelezen berichten een klant heeft.
     */
    private function unreadMarker(User $client, int $count): string
    {
        return 'data-client-id="' . $client->id . '" data-unread="' . $count . '"';
    }

    // ---------- 1. Ongelezen per klant ----------

    public function test_medewerker_ziet_per_klant_hoeveel_berichten_ongelezen_zijn(): void
    {
        $admin = $this->admin();
        $anna = $this->client('Anna Achternaam');
        $bram = $this->client('Bram Bakker');
        $cees = $this->client('Cees Zonder Berichten');

        $this->clientSends($anna, 'Eerste vraag');
        $this->clientSends($anna, 'Tweede vraag');
        $this->clientSends($bram, 'Vraag van Bram');

        $this->actingAs($admin)->get(route('chat.index'))
            ->assertOk()
            ->assertSee($this->unreadMarker($anna, 2), false)
            ->assertSee($this->unreadMarker($bram, 1), false)
            ->assertSee($this->unreadMarker($cees, 0), false);
    }

    public function test_antwoord_van_gkr_telt_niet_als_ongelezen_voor_de_medewerker(): void
    {
        $admin = $this->admin();
        $anna = $this->client('Anna Achternaam');

        $this->adminReplies($admin, $anna, 'Bericht van GKR');

        $this->actingAs($admin)->get(route('chat.index'))
            ->assertOk()
            ->assertSee($this->unreadMarker($anna, 0), false);
    }

    public function test_het_gesprek_dat_je_opent_is_meteen_gelezen_de_rest_niet(): void
    {
        $admin = $this->admin();
        $anna = $this->client('Anna Achternaam');
        $bram = $this->client('Bram Bakker');

        $this->clientSends($anna, 'Vraag van Anna');
        $this->clientSends($bram, 'Vraag van Bram');

        $this->actingAs($admin)->get(route('chat.index', ['client_id' => $anna->id]))
            ->assertOk()
            ->assertSee($this->unreadMarker($anna, 0), false)
            ->assertSee($this->unreadMarker($bram, 1), false);
    }

    // ---------- 2. Volgorde ----------

    public function test_gesprek_met_ongelezen_berichten_staat_bovenaan(): void
    {
        $admin = $this->admin();
        $anna = $this->client('Anna Achternaam');
        $zoe = $this->client('Zoe Zuidema');

        // Op alfabet staat Anna eerst, maar Zoe heeft een ongelezen bericht
        $this->clientSends($zoe, 'Dringende vraag');

        $this->actingAs($admin)->get(route('chat.index'))
            ->assertOk()
            ->assertSeeInOrder(['Zoe Zuidema', 'Anna Achternaam']);
    }

    public function test_zonder_ongelezen_berichten_staat_het_meest_recente_gesprek_bovenaan(): void
    {
        $admin = $this->admin();
        $anna = $this->client('Anna Achternaam');
        $bram = $this->client('Bram Bakker');
        $this->client('Cees Zonder Berichten');

        $this->adminReplies($admin, $anna, 'Ouder bericht');
        $this->travel(5)->minutes();
        $this->adminReplies($admin, $bram, 'Nieuwer bericht');

        $this->actingAs($admin)->get(route('chat.index'))
            ->assertOk()
            ->assertSeeInOrder(['Bram Bakker', 'Anna Achternaam', 'Cees Zonder Berichten']);
    }

    // ---------- 3. Het laatste bericht in de lijst ----------

    public function test_lijst_toont_het_begin_van_het_laatste_bericht(): void
    {
        $admin = $this->admin();
        $anna = $this->client('Anna Achternaam');

        $this->clientSends($anna, 'Eerste bericht van Anna');
        $this->clientSends($anna, 'Laatste bericht van Anna');

        $response = $this->actingAs($admin)->get(route('chat.index'))->assertOk();

        $response->assertSee('Laatste bericht van Anna');
        $response->assertDontSee('Eerste bericht van Anna');

        // Het bericht in de lijst zien telt niet als lezen
        $this->assertSame(2, Message::where('client_id', $anna->id)->whereNull('read_at')->count());
    }

    public function test_bij_een_antwoord_van_gkr_staat_dat_erbij(): void
    {
        $admin = $this->admin();
        $anna = $this->client('Anna Achternaam');

        $this->clientSends($anna, 'Vraag van de klant');
        $this->adminReplies($admin, $anna, 'Wij bellen je morgen');

        $this->actingAs($admin)->get(route('chat.index'))
            ->assertOk()
            ->assertSee('GKR: Wij bellen je morgen');
    }

    public function test_bij_alleen_een_bijlage_staat_de_bestandsnaam_in_de_lijst(): void
    {
        $admin = $this->admin();
        $anna = $this->client('Anna Achternaam');

        $this->actingAs($anna)->post(route('chat.store'), [
            'files' => [UploadedFile::fake()->create('offerte-oktober.pdf', 20, 'application/pdf')],
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->get(route('chat.index'))
            ->assertOk()
            ->assertSee('Bijlage: offerte-oktober.pdf');
    }

    public function test_lang_bericht_wordt_in_de_lijst_ingekort(): void
    {
        $admin = $this->admin();
        $anna = $this->client('Anna Achternaam');

        $this->clientSends($anna, str_repeat('a', 100) . 'EINDE');

        $this->actingAs($admin)->get(route('chat.index'))
            ->assertOk()
            ->assertDontSee('EINDE');
    }

    public function test_bericht_met_code_wordt_in_de_lijst_nooit_als_code_uitgevoerd(): void
    {
        $admin = $this->admin();
        $anna = $this->client('Anna Achternaam');

        $this->clientSends($anna, '<script>alert(1)</script>');

        $this->actingAs($admin)->get(route('chat.index'))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_klant_zonder_berichten_toont_het_e_mailadres(): void
    {
        $admin = $this->admin();
        $cees = $this->client('Cees Zonder Berichten');

        $this->actingAs($admin)->get(route('chat.index'))
            ->assertOk()
            ->assertSee($cees->email);
    }

    // ---------- 4. Alleen voor GKR ----------

    public function test_klant_krijgt_geen_lijst_met_andere_klanten(): void
    {
        $this->admin();
        $anna = $this->client('Anna Achternaam');
        $bram = $this->client('Bram Bakker');
        $this->clientSends($bram, 'Vraag van Bram');

        $this->actingAs($anna)->get(route('chat.index'))
            ->assertOk()
            ->assertDontSee('Bram Bakker')
            ->assertDontSee('Vraag van Bram')
            ->assertDontSee('data-unread', false);
    }
}