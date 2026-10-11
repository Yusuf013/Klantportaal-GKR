<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test de rode teller in de zijbalk op pagina's buiten de chat.
 * Elke pagina vraagt af en toe het aantal ongelezen berichten op (route chat.unread),
 * zodat je zonder herladen ziet dat er een bericht is binnengekomen.
 */
class ChatUnreadBadgeTest extends TestCase
{
    use RefreshDatabase;

    // ---------- Hulpfuncties ----------

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true, 'name' => 'Stijn Medewerker']);
    }

    private function client(string $name = 'Anna Achternaam'): User
    {
        return User::factory()->create(['is_admin' => false, 'name' => $name]);
    }

    private function fromClient(User $client, string $body): Message
    {
        return Message::create(['client_id' => $client->id, 'sender_id' => $client->id, 'body' => $body]);
    }

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
     * Wat elke pagina af en toe doet: het aantal ongelezen berichten opvragen.
     */
    private function unread(User $user)
    {
        return $this->actingAs($user)->getJson(route('chat.unread'));
    }

    // ---------- 1. Het getal ----------

    public function test_gast_krijgt_geen_teller(): void
    {
        $this->getJson(route('chat.unread'))->assertUnauthorized();
    }

    public function test_klant_krijgt_het_aantal_ongelezen_antwoorden_uit_het_eigen_gesprek(): void
    {
        $admin = $this->admin();
        $anna = $this->client('Anna Achternaam');
        $bram = $this->client('Bram Bakker');

        $this->fromGkr($admin, $anna, 'Eerste antwoord aan Anna');
        $this->fromGkr($admin, $anna, 'Tweede antwoord aan Anna');
        // Telt niet mee voor Anna: haar eigen bericht en een antwoord aan een andere klant
        $this->fromClient($anna, 'Vraag van Anna');
        $this->fromGkr($admin, $bram, 'Geheim antwoord aan Bram');

        // Alleen een getal: geen namen en geen berichten
        $this->unread($anna)->assertOk()->assertExactJson(['unread_total' => 2]);
    }

    public function test_medewerker_krijgt_de_ongelezen_berichten_van_alle_klanten(): void
    {
        $admin = $this->admin();
        $anna = $this->client('Anna Achternaam');
        $bram = $this->client('Bram Bakker');

        $this->fromClient($anna, 'Eerste vraag van Anna');
        $this->fromClient($anna, 'Tweede vraag van Anna');
        $this->fromClient($bram, 'Vraag van Bram');
        // Telt niet mee: een antwoord van GKR zelf
        $this->fromGkr($admin, $anna, 'Antwoord van GKR');

        $this->unread($admin)->assertOk()->assertExactJson(['unread_total' => 3]);
    }

    public function test_opvragen_van_de_teller_markeert_niets_als_gelezen(): void
    {
        $admin = $this->admin();
        $anna = $this->client();
        $reply = $this->fromGkr($admin, $anna, 'Antwoord van GKR');

        $this->unread($anna)->assertOk();
        $this->unread($anna)->assertExactJson(['unread_total' => 1]);

        $this->assertNull($reply->fresh()->read_at);
    }

    public function test_na_het_openen_van_de_chat_staat_de_teller_op_nul(): void
    {
        $admin = $this->admin();
        $anna = $this->client();
        $this->fromGkr($admin, $anna, 'Antwoord van GKR');

        $this->actingAs($anna)->get(route('chat.index'))->assertOk();

        $this->unread($anna)->assertExactJson(['unread_total' => 0]);
    }

    // ---------- 2. De pagina's ----------

    public function test_andere_paginas_werken_de_teller_zelf_bij(): void
    {
        // De profielpagina als voorbeeld van "een andere pagina dan de chat"
        $this->actingAs($this->client())->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('id="chat-unread-badge"', false)
            ->assertSee('id="chat-unread-poll"', false);
    }

    public function test_chatpagina_gebruikt_dit_script_niet_omdat_de_chat_de_teller_al_bijwerkt(): void
    {
        $this->actingAs($this->client())->get(route('chat.index'))
            ->assertOk()
            ->assertSee('id="chat-unread-badge"', false)
            ->assertDontSee('id="chat-unread-poll"', false);
    }
}