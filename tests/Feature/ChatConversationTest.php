<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Test de opzet van de chat: één gesprek per klant met GKR als team.
 * (Wie bij welke bijlage mag, staat in ChatSecurityTest.)
 */
class ChatConversationTest extends TestCase
{
    use RefreshDatabase;

    // ---------- Hulpfuncties ----------

    protected function setUp(): void
    {
        parent::setUp();

        // Nep-schijven: tests schrijven nooit echte bestanden
        Storage::fake('local');
        Storage::fake('public');
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function client(): User
    {
        return User::factory()->create(['is_admin' => false]);
    }

    /**
     * De klant stuurt een bericht via het formulier.
     */
    private function clientSends(User $client, string $body)
    {
        return $this->actingAs($client)->post(route('chat.store'), ['body' => $body]);
    }

    /**
     * Een medewerker antwoordt in het gesprek van deze klant.
     */
    private function adminReplies(User $admin, User $client, string $body)
    {
        return $this->actingAs($admin)->post(route('chat.store'), ['receiver_id' => $client->id, 'body' => $body]);
    }

    private function messageWith(string $body): Message
    {
        return Message::where('body', $body)->firstOrFail();
    }

    // ---------- 1. Versturen ----------

    public function test_bericht_van_klant_komt_in_het_eigen_gesprek_zonder_vaste_ontvanger(): void
    {
        $this->admin();
        $client = $this->client();

        $this->clientSends($client, 'Vraag van de klant')->assertSessionHasNoErrors();

        $message = $this->messageWith('Vraag van de klant');

        $this->assertSame($client->id, (int) $message->client_id);
        $this->assertSame($client->id, (int) $message->sender_id);
        // Niet meer gericht aan één medewerker: GKR leest als team
        $this->assertNull($message->receiver_id);
    }

    public function test_klant_kan_ook_een_bericht_sturen_als_er_nog_geen_medewerker_bestaat(): void
    {
        $client = $this->client();

        $this->clientSends($client, 'Is daar iemand?')->assertSessionHasNoErrors();

        $this->assertDatabaseCount('messages', 1);
    }

    public function test_antwoord_van_medewerker_komt_in_het_gesprek_van_de_klant(): void
    {
        $admin = $this->admin();
        $client = $this->client();

        $this->adminReplies($admin, $client, 'Antwoord van GKR')->assertSessionHasNoErrors();

        $message = $this->messageWith('Antwoord van GKR');

        $this->assertSame($client->id, (int) $message->client_id);
        $this->assertSame($admin->id, (int) $message->sender_id);
        $this->assertSame($client->id, (int) $message->receiver_id);
    }

    // ---------- 2. Wie ziet welk gesprek ----------

    public function test_klant_ziet_alleen_het_eigen_gesprek(): void
    {
        $admin = $this->admin();
        $anna = $this->client();
        $bram = $this->client();

        $this->clientSends($anna, 'Bericht van Anna');
        $this->clientSends($bram, 'Bericht van Bram');
        $this->adminReplies($admin, $anna, 'Antwoord aan Anna');
        $this->adminReplies($admin, $bram, 'Antwoord aan Bram');

        $this->actingAs($anna)->get(route('chat.index'))
            ->assertOk()
            ->assertSee('Bericht van Anna')
            ->assertSee('Antwoord aan Anna')
            ->assertDontSee('Bericht van Bram')
            ->assertDontSee('Antwoord aan Bram');
    }

    public function test_elke_medewerker_ziet_hetzelfde_gesprek_van_een_klant(): void
    {
        $stijn = $this->admin();
        $owen = $this->admin();
        $client = $this->client();

        $this->clientSends($client, 'Vraag van de klant');
        $this->adminReplies($stijn, $client, 'Antwoord van Stijn');

        // Owen heeft zelf niets gestuurd of ontvangen, maar ziet het hele gesprek
        $this->actingAs($owen)->get(route('chat.index', ['client_id' => $client->id]))
            ->assertOk()
            ->assertSee('Vraag van de klant')
            ->assertSee('Antwoord van Stijn');
    }

    public function test_medewerker_ziet_in_een_gesprek_geen_berichten_van_een_andere_klant(): void
    {
        $admin = $this->admin();
        $anna = $this->client();
        $bram = $this->client();

        $this->clientSends($anna, 'Bericht van Anna');
        $this->clientSends($bram, 'Eerste bericht van Bram');
        $this->clientSends($bram, 'Laatste bericht van Bram');

        $response = $this->actingAs($admin)->get(route('chat.index', ['client_id' => $anna->id]))
            ->assertOk()
            ->assertSee('Bericht van Anna');

        // In de lijst links staat van elke klant alleen het begin van het LAATSTE bericht.
        // Een ouder bericht van Bram kan dus alleen op de pagina staan als het gesprek
        // van Bram getoond wordt, en dat mag hier niet: het gesprek van Anna staat open.
        $response->assertDontSee('Eerste bericht van Bram');

        // Het laatste bericht van Bram staat precies één keer op de pagina: in de lijst
        $this->assertSame(1, substr_count($response->getContent(), 'Laatste bericht van Bram'));
    }

    public function test_een_medewerker_in_de_url_wordt_niet_als_klant_geopend(): void
    {
        $admin = $this->admin();
        $otherAdmin = $this->admin();

        $this->actingAs($admin)->get(route('chat.index', ['client_id' => $otherAdmin->id]))
            ->assertOk()
            ->assertSee('Kies een klant om het gesprek te openen.');
    }

    // ---------- 3. Gelezen ----------

    public function test_chat_openen_zonder_klant_te_kiezen_markeert_niets_als_gelezen(): void
    {
        $admin = $this->admin();
        $client = $this->client();
        $this->clientSends($client, 'Nog niet gelezen');

        // Alleen op "Chat" klikken: er is nog geen gesprek geopend.
        // (De medewerker ziet in de lijst wel het begin van het bericht, maar dat telt niet als gelezen.)
        $this->actingAs($admin)->get(route('chat.index'))
            ->assertOk()
            ->assertSee('Kies een klant om het gesprek te openen.');

        $this->assertNull($this->messageWith('Nog niet gelezen')->read_at);
    }

    public function test_gesprek_openen_markeert_alleen_de_berichten_van_die_klant_als_gelezen(): void
    {
        $admin = $this->admin();
        $anna = $this->client();
        $bram = $this->client();

        $this->clientSends($anna, 'Bericht van Anna');
        $this->clientSends($bram, 'Bericht van Bram');
        $this->adminReplies($admin, $anna, 'Antwoord aan Anna');

        $this->actingAs($admin)->get(route('chat.index', ['client_id' => $anna->id]))->assertOk();

        $this->assertNotNull($this->messageWith('Bericht van Anna')->read_at);
        $this->assertNull($this->messageWith('Bericht van Bram')->read_at);
        // Het eigen antwoord van GKR is pas gelezen als de klant het opent
        $this->assertNull($this->messageWith('Antwoord aan Anna')->read_at);
    }

    public function test_klant_opent_de_chat_en_alleen_de_antwoorden_van_gkr_zijn_gelezen(): void
    {
        $admin = $this->admin();
        $client = $this->client();

        $this->clientSends($client, 'Vraag van de klant');
        $this->adminReplies($admin, $client, 'Antwoord van GKR');

        $this->actingAs($client)->get(route('chat.index'))->assertOk();

        $this->assertNotNull($this->messageWith('Antwoord van GKR')->read_at);
        // Het eigen bericht van de klant is pas gelezen als GKR het opent
        $this->assertNull($this->messageWith('Vraag van de klant')->read_at);
    }

    // ---------- 4. De teller in de zijbalk ----------

    public function test_teller_van_medewerker_telt_ongelezen_berichten_van_alle_klanten(): void
    {
        $admin = $this->admin();
        $anna = $this->client();
        $bram = $this->client();

        $this->clientSends($anna, 'Bericht van Anna');
        $this->clientSends($bram, 'Bericht van Bram');
        $this->adminReplies($admin, $anna, 'Antwoord aan Anna'); // eigen antwoord telt niet mee

        $this->assertSame(2, Message::unreadCountFor($admin));
        // Een collega ziet dezelfde teller: GKR leest als team
        $this->assertSame(2, Message::unreadCountFor($this->admin()));
    }

    public function test_teller_van_klant_telt_alleen_ongelezen_antwoorden_in_het_eigen_gesprek(): void
    {
        $admin = $this->admin();
        $anna = $this->client();
        $bram = $this->client();

        $this->clientSends($anna, 'Bericht van Anna'); // eigen bericht telt niet mee
        $this->adminReplies($admin, $anna, 'Eerste antwoord aan Anna');
        $this->adminReplies($admin, $anna, 'Tweede antwoord aan Anna');
        $this->adminReplies($admin, $bram, 'Antwoord aan Bram');

        $this->assertSame(2, Message::unreadCountFor($anna));
        $this->assertSame(1, Message::unreadCountFor($bram));
    }

    // ---------- 5. Accounts die verdwijnen of van rol wisselen ----------

    public function test_medewerker_verwijderen_laat_het_gesprek_van_de_klant_heel(): void
    {
        $admin = $this->admin();
        $client = $this->client();

        $this->clientSends($client, 'Vraag van de klant');
        $this->adminReplies($admin, $client, 'Wij pakken het op');

        // Voorheen verdwenen hierbij de berichten van ALLE klanten
        $admin->delete();

        $this->assertDatabaseCount('messages', 2);
        $this->assertNull($this->messageWith('Wij pakken het op')->sender_id);

        $this->actingAs($client)->get(route('chat.index'))
            ->assertOk()
            ->assertSee('Vraag van de klant')
            ->assertSee('Wij pakken het op');
    }

    public function test_klant_verwijderen_ruimt_het_gesprek_en_de_bijlagen_op(): void
    {
        $this->admin();
        $anna = $this->client();
        $bram = $this->client();

        $this->actingAs($anna)->post(route('chat.store'), [
            'files' => [UploadedFile::fake()->create('offerte.pdf', 20, 'application/pdf')],
        ])->assertSessionHasNoErrors();
        $this->clientSends($bram, 'Bericht van Bram');

        $path = Message::where('client_id', $anna->id)->firstOrFail()->file_path;
        Storage::disk('local')->assertExists($path);

        $anna->delete();

        // Het gesprek van Anna en haar bijlage zijn weg, dat van Bram staat er nog
        $this->assertSame(0, Message::where('client_id', $anna->id)->count());
        Storage::disk('local')->assertMissing($path);
        $this->assertSame(1, Message::where('client_id', $bram->id)->count());
    }

    public function test_medewerker_die_klant_wordt_ziet_geen_berichten_van_klanten(): void
    {
        $admin = $this->admin();
        $client = $this->client();
        $this->clientSends($client, 'Vertrouwelijk bericht');

        // Voorheen stond dit bericht dan in de eigen chat van deze ex-medewerker
        $admin->is_admin = false;
        $admin->save();

        $this->actingAs($admin->fresh())->get(route('chat.index'))
            ->assertOk()
            ->assertDontSee('Vertrouwelijk bericht');
    }

    public function test_klant_kan_bijlage_van_gkr_openen_ook_als_die_medewerker_weg_is(): void
    {
        $admin = $this->admin();
        $anna = $this->client();
        $bram = $this->client();

        $this->actingAs($admin)->post(route('chat.store'), [
            'receiver_id' => $anna->id,
            'files'       => [UploadedFile::fake()->create('rapport.pdf', 20, 'application/pdf')],
        ])->assertSessionHasNoErrors();

        $message = Message::firstOrFail();
        $admin->delete();

        $this->actingAs($anna)->get(route('chat.download', $message))->assertOk();
        // Een andere klant komt er nog steeds niet bij
        $this->actingAs($bram)->get(route('chat.download', $message))->assertForbidden();
    }

    // ---------- 6. Rem op versturen ----------

    public function test_na_twintig_berichten_in_een_minuut_komt_er_een_melding(): void
    {
        $client = $this->client();

        for ($i = 1; $i <= 20; $i++) {
            $this->clientSends($client, "Bericht {$i}")->assertSessionHasNoErrors();
        }

        $this->clientSends($client, 'Bericht 21')->assertSessionHasErrors('body');
        $this->assertDatabaseCount('messages', 20);

        // Een minuut later kan het weer
        $this->travel(61)->seconds();

        $this->clientSends($client, 'Bericht 21')->assertSessionHasNoErrors();
        $this->assertDatabaseCount('messages', 21);
    }

    public function test_rem_geldt_per_gebruiker(): void
    {
        $anna = $this->client();
        $bram = $this->client();

        for ($i = 1; $i <= 20; $i++) {
            $this->clientSends($anna, "Bericht {$i}");
        }

        // Anna zit aan haar limiet, Bram heeft daar geen last van
        $this->clientSends($bram, 'Bericht van Bram')->assertSessionHasNoErrors();
    }

    public function test_te_veel_bijlagen_in_korte_tijd_wordt_tegengehouden(): void
    {
        $client = $this->client();

        $files = fn (int $count) => array_map(
            fn ($n) => UploadedFile::fake()->create("bestand{$n}.pdf", 10, 'application/pdf'),
            range(1, $count)
        );

        // 4 keer 5 bestanden = 20 bestanden: dat mag
        for ($i = 1; $i <= 4; $i++) {
            $this->actingAs($client)->post(route('chat.store'), ['files' => $files(5)])->assertSessionHasNoErrors();
        }

        // Het 21e bestand binnen tien minuten niet meer
        $this->actingAs($client)->post(route('chat.store'), ['files' => $files(1)])->assertSessionHasErrors('body');

        $this->assertDatabaseCount('messages', 20);
    }

    // ---------- 7. Bestaande berichten omzetten ----------

    public function test_bestaande_berichten_krijgen_bij_het_omzetten_het_juiste_gesprek(): void
    {
        $admin = $this->admin();
        $client = $this->client();

        // Zo stonden berichten er voorheen in: zonder gesprek, en een bericht van
        // een klant was gericht aan één medewerker
        $row = fn (User $sender, User $receiver, string $body) => DB::table('messages')->insertGetId([
            'sender_id'   => $sender->id,
            'receiver_id' => $receiver->id,
            'body'        => $body,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $fromClient = $row($client, $admin, 'Oud bericht van de klant');
        $fromAdmin = $row($admin, $client, 'Oud antwoord van GKR');

        $migration = require database_path('migrations/2026_10_11_190000_add_client_id_to_messages_table.php');
        $migration->backfill();

        $fromClient = Message::findOrFail($fromClient);
        $fromAdmin = Message::findOrFail($fromAdmin);

        // Beide berichten horen nu bij het gesprek van de klant
        $this->assertSame($client->id, (int) $fromClient->client_id);
        $this->assertSame($client->id, (int) $fromAdmin->client_id);

        // Het bericht van de klant hangt niet meer aan het account van de medewerker
        $this->assertNull($fromClient->receiver_id);
        $this->assertSame($client->id, (int) $fromAdmin->receiver_id);

        // De klant ziet het hele oude gesprek nog
        $this->actingAs($client)->get(route('chat.index'))
            ->assertOk()
            ->assertSee('Oud bericht van de klant')
            ->assertSee('Oud antwoord van GKR');
    }
}