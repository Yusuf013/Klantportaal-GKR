<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Test de chat zonder herladen: de browser vraagt om de paar seconden aan de server
 * of er iets nieuws is (route chat.messages) en verstuurt berichten op de achtergrond.
 *
 * De tests doen hetzelfde als de browser: een verzoek met "ik wil JSON terug".
 */
class ChatLiveTest extends TestCase
{
    use RefreshDatabase;

    // ---------- Hulpfuncties ----------

    protected function setUp(): void
    {
        parent::setUp();

        // Nep-schijven: tests schrijven nooit echte bestanden
        Storage::fake('local');
        Storage::fake('public');

        // Vaste datum en tijd (maandag 12 oktober 2026, 12:00), zodat we weten wat "vandaag" is
        $this->travelTo(Carbon::parse('2026-10-12 12:00:00', 'Europe/Amsterdam'));
    }

    private function admin(string $name = 'Stijn Medewerker'): User
    {
        return User::factory()->create(['is_admin' => true, 'name' => $name]);
    }

    private function client(string $name = 'Anna Achternaam'): User
    {
        return User::factory()->create(['is_admin' => false, 'name' => $name]);
    }

    /**
     * De klant schrijft een bericht (rechtstreeks in de database, dus zonder de rem op versturen).
     */
    private function fromClient(User $client, string $body): Message
    {
        return Message::create(['client_id' => $client->id, 'sender_id' => $client->id, 'body' => $body]);
    }

    /**
     * Een medewerker antwoordt in het gesprek van deze klant.
     */
    private function fromGkr(User $admin, User $client, string $body): Message
    {
        return Message::create([
            'client_id'   => $client->id,
            'sender_id'   => $admin->id,
            'receiver_id' => $client->id,
            'body'        => $body,
        ]);
    }

    /**
     * Wat de chat in de browser om de paar seconden doet: vragen of er iets nieuws is.
     */
    private function poll(User $user, array $query = [])
    {
        return $this->actingAs($user)->getJson(route('chat.messages', $query));
    }

    /**
     * Versturen zoals de chat in de browser dat doet: een gewoon formulier, maar met "ik wil JSON terug".
     */
    private function sendLive(User $user, array $data)
    {
        return $this->actingAs($user)->post(route('chat.store'), $data, ['Accept' => 'application/json']);
    }

    // ---------- 1. Nieuwe berichten ophalen ----------

    public function test_gast_krijgt_geen_berichten(): void
    {
        $this->getJson(route('chat.messages'))->assertUnauthorized();
    }

    public function test_klant_haalt_nieuwe_berichten_uit_het_eigen_gesprek_op(): void
    {
        $admin = $this->admin();
        $anna = $this->client();
        $reply = $this->fromGkr($admin, $anna, 'Antwoord van GKR');

        $response = $this->poll($anna)->assertOk();

        $this->assertStringContainsString('Antwoord van GKR', $response->json('html'));
        $this->assertStringContainsString('data-message-id="' . $reply->id . '"', $response->json('html'));
        $this->assertSame($reply->id, $response->json('last_id'));
    }

    public function test_alleen_berichten_na_het_laatste_bekende_bericht_komen_terug(): void
    {
        $anna = $this->client();
        $first = $this->fromClient($anna, 'Eerste bericht');
        $second = $this->fromClient($anna, 'Tweede bericht');

        $response = $this->poll($anna, ['after' => $first->id])->assertOk();

        $this->assertStringContainsString('Tweede bericht', $response->json('html'));
        $this->assertStringNotContainsString('Eerste bericht', $response->json('html'));
        $this->assertSame($second->id, $response->json('last_id'));
    }

    public function test_is_er_niets_nieuws_dan_komt_er_geen_opmaak_terug(): void
    {
        $anna = $this->client();
        $last = $this->fromClient($anna, 'Laatste bericht');

        $response = $this->poll($anna, ['after' => $last->id])->assertOk();

        $this->assertSame('', $response->json('html'));
        $this->assertSame($last->id, $response->json('last_id'));
    }

    // ---------- 2. Veiligheid: wie krijgt welk gesprek ----------

    public function test_klant_kan_via_de_url_niet_het_gesprek_van_een_ander_ophalen(): void
    {
        $anna = $this->client('Anna Achternaam');
        $bram = $this->client('Bram Bakker');
        $this->fromClient($anna, 'Vraag van Anna');
        $secret = $this->fromClient($bram, 'Geheim van Bram');

        // Anna zet het nummer van Bram in de URL
        $response = $this->poll($anna, ['client_id' => $bram->id, 'seen' => 1])->assertOk();

        // Ze krijgt gewoon haar eigen gesprek
        $this->assertStringContainsString('Vraag van Anna', $response->json('html'));
        $this->assertStringNotContainsString('Geheim van Bram', $response->getContent());

        // En het bericht van Bram is niet als gelezen gemarkeerd
        $this->assertNull($secret->fresh()->read_at);
    }

    public function test_klant_krijgt_nooit_de_lijst_met_klanten(): void
    {
        $anna = $this->client('Anna Achternaam');
        $bram = $this->client('Bram Bakker');
        $this->fromClient($bram, 'Vraag van Bram');

        $response = $this->poll($anna)->assertOk();

        $this->assertNull($response->json('clients_html'));
        $this->assertStringNotContainsString('Bram Bakker', $response->getContent());
        $this->assertStringNotContainsString($bram->email, $response->getContent());
    }

    public function test_medewerker_haalt_het_gesprek_van_de_gekozen_klant_op(): void
    {
        $admin = $this->admin();
        $anna = $this->client('Anna Achternaam');
        $bram = $this->client('Bram Bakker');
        $this->fromClient($anna, 'Vraag van Anna');
        $this->fromClient($bram, 'Vraag van Bram');

        $response = $this->poll($admin, ['client_id' => $anna->id])->assertOk();

        $this->assertStringContainsString('Vraag van Anna', $response->json('html'));
        $this->assertStringNotContainsString('Vraag van Bram', $response->json('html'));
    }

    public function test_medewerker_zonder_gekozen_klant_krijgt_alleen_de_lijst(): void
    {
        $admin = $this->admin();
        $anna = $this->client('Anna Achternaam');
        $this->fromClient($anna, 'Vraag van Anna');

        $response = $this->poll($admin, ['seen' => 1])->assertOk();

        $this->assertSame('', $response->json('html'));
        $this->assertSame(0, $response->json('last_id'));
        $this->assertNull($response->json('read_up_to'));

        // De lijst komt wel mee, met de teller en het laatste bericht van Anna
        $list = $response->json('clients_html');
        $this->assertStringContainsString('data-client-id="' . $anna->id . '" data-unread="1"', $list);
        $this->assertStringContainsString('Vraag van Anna', $list);

        // Er staat geen gesprek open, dus er is niets gelezen
        $this->assertSame(1, $response->json('unread_total'));
    }

    public function test_nummer_dat_geen_klant_is_geeft_404(): void
    {
        $admin = $this->admin();
        $colleague = $this->admin('Owen Collega');

        // Het nummer van een collega (geen klant)
        $this->poll($admin, ['client_id' => $colleague->id])->assertNotFound();
        // Een nummer dat niet bestaat
        $this->poll($admin, ['client_id' => 99999])->assertNotFound();
    }

    // ---------- 3. Gelezen ----------

    public function test_alleen_als_het_gesprek_in_beeld_is_telt_een_bericht_als_gelezen(): void
    {
        $admin = $this->admin();
        $anna = $this->client();
        $question = $this->fromClient($anna, 'Vraag van de klant');

        // Het tabblad staat op de achtergrond (seen=0): niet gelezen
        $this->poll($admin, ['client_id' => $anna->id, 'seen' => 0])->assertOk();
        $this->assertNull($question->fresh()->read_at);

        // De medewerker heeft het gesprek echt in beeld (seen=1): gelezen
        $this->poll($admin, ['client_id' => $anna->id, 'seen' => 1])->assertOk();
        $this->assertNotNull($question->fresh()->read_at);
    }

    public function test_klant_ziet_tot_waar_gkr_heeft_gelezen(): void
    {
        $admin = $this->admin();
        $anna = $this->client();
        $question = $this->fromClient($anna, 'Vraag van de klant');

        // Nog door niemand van GKR geopend
        $this->assertNull($this->poll($anna, ['seen' => 1])->json('read_up_to'));

        // Een medewerker opent het gesprek
        $this->poll($admin, ['client_id' => $anna->id, 'seen' => 1])->assertOk();

        $this->assertSame($question->id, $this->poll($anna, ['seen' => 1])->json('read_up_to'));
    }

    public function test_medewerker_ziet_tot_waar_de_klant_heeft_gelezen(): void
    {
        $admin = $this->admin();
        $anna = $this->client();
        $reply = $this->fromGkr($admin, $anna, 'Antwoord van GKR');

        // De klant heeft het antwoord nog niet gezien
        $this->assertNull($this->poll($admin, ['client_id' => $anna->id, 'seen' => 1])->json('read_up_to'));

        // De klant opent de chat
        $this->poll($anna, ['seen' => 1])->assertOk();

        $this->assertSame($reply->id, $this->poll($admin, ['client_id' => $anna->id, 'seen' => 1])->json('read_up_to'));
    }

    public function test_teller_voor_de_zijbalk_komt_mee_voor_de_klant(): void
    {
        $admin = $this->admin();
        $anna = $this->client();
        $this->fromGkr($admin, $anna, 'Eerste antwoord');
        $this->fromGkr($admin, $anna, 'Tweede antwoord');

        // Tabblad op de achtergrond: twee ongelezen antwoorden
        $this->assertSame(2, $this->poll($anna, ['seen' => 0])->json('unread_total'));
        // Chat in beeld: alles is gelezen
        $this->assertSame(0, $this->poll($anna, ['seen' => 1])->json('unread_total'));
    }

    public function test_teller_voor_de_zijbalk_komt_mee_voor_de_medewerker(): void
    {
        $admin = $this->admin();
        $anna = $this->client('Anna Achternaam');
        $bram = $this->client('Bram Bakker');
        $this->fromClient($anna, 'Vraag van Anna');
        $this->fromClient($bram, 'Vraag van Bram');

        $this->assertSame(2, $this->poll($admin)->json('unread_total'));

        // Het gesprek van Anna staat open en in beeld: alleen dat van Bram is nog ongelezen
        $response = $this->poll($admin, ['client_id' => $anna->id, 'seen' => 1]);

        $this->assertSame(1, $response->json('unread_total'));
        $this->assertStringContainsString('data-client-id="' . $anna->id . '" data-unread="0"', $response->json('clients_html'));
        $this->assertStringContainsString('data-client-id="' . $bram->id . '" data-unread="1"', $response->json('clients_html'));
    }

    // ---------- 4. Versturen zonder herladen ----------

    public function test_versturen_zonder_herladen_geeft_een_kort_antwoord(): void
    {
        $anna = $this->client();

        $this->sendLive($anna, ['body' => 'Hallo GKR'])
            ->assertCreated()
            ->assertExactJson(['ok' => true]);

        $message = Message::firstOrFail();
        $this->assertSame('Hallo GKR', $message->body);
        $this->assertSame($anna->id, (int) $message->client_id);
    }

    public function test_medewerker_verstuurt_zonder_herladen_naar_de_gekozen_klant(): void
    {
        $admin = $this->admin();
        $anna = $this->client();

        $this->sendLive($admin, ['receiver_id' => $anna->id, 'body' => 'Wij pakken het op'])->assertCreated();

        // De klant krijgt het antwoord bij de volgende ronde binnen
        $this->assertStringContainsString('Wij pakken het op', $this->poll($anna)->json('html'));
    }

    public function test_gewoon_formulier_werkt_nog_met_herladen(): void
    {
        $anna = $this->client();

        // Zonder JavaScript: het formulier stuurt terug naar de pagina
        $this->actingAs($anna)->from(route('chat.index'))->post(route('chat.store'), ['body' => 'Hallo'])
            ->assertRedirect(route('chat.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('messages', 1);
    }

    public function test_leeg_bericht_geeft_een_nederlandse_melding(): void
    {
        $response = $this->sendLive($this->client(), ['body' => ''])->assertStatus(422);

        $this->assertSame('Vul een bericht in of kies ten minste één bestand.', $response->json('errors.body.0'));
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_te_snel_versturen_geeft_een_nederlandse_melding(): void
    {
        $anna = $this->client();

        for ($i = 1; $i <= 20; $i++) {
            $this->sendLive($anna, ['body' => "Bericht {$i}"])->assertCreated();
        }

        $response = $this->sendLive($anna, ['body' => 'Bericht 21'])->assertStatus(429);

        $this->assertStringContainsString('te snel', $response->json('message'));
        $this->assertDatabaseCount('messages', 20);
    }

    public function test_te_lang_bericht_geeft_een_nederlandse_melding(): void
    {
        $this->sendLive($this->client(), ['body' => str_repeat('a', 2001)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['body' => 'maximaal 2000 tekens']);

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_verkeerd_bestandstype_geeft_een_nederlandse_melding(): void
    {
        $this->sendLive($this->client(), [
            'files' => [UploadedFile::fake()->create('notities.txt', 10, 'text/plain')],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['files.0' => 'Dit bestandstype kan niet worden verstuurd']);

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_te_groot_bestand_geeft_een_nederlandse_melding(): void
    {
        // 10241 KB is net meer dan de 10 MB die mag
        $this->sendLive($this->client(), [
            'files' => [UploadedFile::fake()->create('groot.pdf', 10241, 'application/pdf')],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['files.0' => 'maximaal 10 MB']);

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_te_veel_bestanden_tegelijk_geeft_een_nederlandse_melding(): void
    {
        $files = array_map(
            fn ($n) => UploadedFile::fake()->create("bestand{$n}.pdf", 10, 'application/pdf'),
            range(1, 6)
        );

        $this->sendLive($this->client(), ['files' => $files])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['files' => 'maximaal 5 bestanden']);

        $this->assertDatabaseCount('messages', 0);
    }

    // ---------- 5. De opmaak die de server terugstuurt ----------

    public function test_bericht_met_code_wordt_onschadelijk_gemaakt(): void
    {
        $admin = $this->admin();
        $anna = $this->client();
        $this->fromClient($anna, '<script>alert(1)</script>');

        $response = $this->poll($admin, ['client_id' => $anna->id])->assertOk();

        // In het bericht zelf
        $this->assertStringNotContainsString('<script>alert(1)</script>', $response->json('html'));
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $response->json('html'));
        // En in de lijst met gesprekken (het laatste bericht staat daar als voorproefje)
        $this->assertStringNotContainsString('<script>alert(1)</script>', $response->json('clients_html'));
    }

    public function test_bijlage_komt_mee_zonder_de_plek_op_de_schijf_te_verraden(): void
    {
        $admin = $this->admin();
        $anna = $this->client();

        $this->sendLive($anna, [
            'body'  => 'Zie de bijlage',
            'files' => [UploadedFile::fake()->create('offerte.pdf', 20, 'application/pdf')],
        ])->assertCreated();

        $html = $this->poll($admin, ['client_id' => $anna->id])->json('html');

        $this->assertStringContainsString('offerte.pdf', $html);
        $this->assertStringContainsString('Zie de bijlage', $html);
        // Geen map of pad van de schijf: het bestand gaat alleen via de beveiligde routes
        $this->assertStringNotContainsString('chat-files', $html);
        $this->assertStringNotContainsString('storage', $html);
    }

    public function test_datum_staat_alleen_boven_het_eerste_bericht_van_een_dag(): void
    {
        $admin = $this->admin();
        $anna = $this->client();

        // Het is maandag 12 oktober 2026
        $old = $this->fromClient($anna, 'Bericht van vorige week');
        $old->forceFill(['created_at' => Carbon::parse('2026-10-05 10:00:00', 'Europe/Amsterdam')])->save();

        $yesterday = $this->fromClient($anna, 'Bericht van gisteren');
        $yesterday->forceFill(['created_at' => Carbon::parse('2026-10-11 09:00:00', 'Europe/Amsterdam')])->save();

        $today = $this->fromClient($anna, 'Eerste bericht van vandaag');
        $this->fromClient($anna, 'Tweede bericht van vandaag');

        $html = $this->poll($admin, ['client_id' => $anna->id])->json('html');

        $this->assertSame(1, substr_count($html, '5 oktober 2026'));
        $this->assertSame(1, substr_count($html, 'Gisteren'));
        // Twee berichten van vandaag, maar de datum staat er maar één keer boven
        $this->assertSame(1, substr_count($html, 'Vandaag'));

        // Haalt de browser alleen het laatste bericht op, dan hoort daar geen datum boven:
        // het bericht ervoor is van dezelfde dag
        $html = $this->poll($admin, ['client_id' => $anna->id, 'after' => $today->id])->json('html');

        $this->assertStringContainsString('Tweede bericht van vandaag', $html);
        $this->assertStringNotContainsString('Vandaag', $html);
    }

    // ---------- 6. De pagina zelf ----------

    public function test_chatpagina_is_klaar_voor_de_chat_zonder_herladen(): void
    {
        $admin = $this->admin();
        $anna = $this->client();
        $this->fromGkr($admin, $anna, 'Antwoord van GKR');

        $this->actingAs($anna)->get(route('chat.index'))
            ->assertOk()
            ->assertSee('id="chat-form"', false)
            ->assertSee('id="chat-unread-badge"', false)
            ->assertSee('messagesUrl', false)
            ->assertSee('Vandaag')
            // Er stond altijd "Online", ook als er niemand online was
            ->assertDontSee('Online');
    }

    public function test_medewerker_heeft_een_zoekbalk_met_naam_en_e_mail_per_klant(): void
    {
        $admin = $this->admin();
        $anna = User::factory()->create(['is_admin' => false, 'name' => 'Anna Achternaam', 'email' => 'Anna@Example.com']);

        $this->actingAs($admin)->get(route('chat.index'))
            ->assertOk()
            ->assertSee('id="chat-search"', false)
            // Kleine letters, zodat zoeken niet op hoofdletters let
            ->assertSee('data-search="anna achternaam anna@example.com"', false);
    }
}