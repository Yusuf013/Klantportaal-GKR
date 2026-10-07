<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChatSecurityTest extends TestCase
{
    use RefreshDatabase;

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

    private function fileMessage(User $sender, User $receiver, string $disk = 'local', string $path = 'chat-files/test.pdf', string $name = 'offerte.pdf'): Message
    {
        Storage::disk($disk)->put($path, '%PDF-1.4 test');

        return Message::create([
            'sender_id'   => $sender->id,
            'receiver_id' => $receiver->id,
            'body'        => null,
            'file_path'   => $path,
            'file_name'   => $name,
            'file_size'   => 13,
            'file_type'   => 'pdf',
        ]);
    }

    public function test_gast_wordt_naar_login_gestuurd_bij_download(): void
    {
        $message = $this->fileMessage($this->client(), $this->admin());

        $this->get(route('chat.download', $message))->assertRedirect(route('login'));
    }

    public function test_andere_klant_kan_bijlage_niet_downloaden_of_bekijken(): void
    {
        $message = $this->fileMessage($this->client(), $this->admin());
        $other = $this->client();

        $this->actingAs($other)->get(route('chat.download', $message))->assertForbidden();
        $this->actingAs($other)->get(route('chat.preview', $message))->assertForbidden();
    }

    public function test_afzender_kan_eigen_bijlage_downloaden_en_bekijken(): void
    {
        $client = $this->client();
        $message = $this->fileMessage($client, $this->admin());

        $this->actingAs($client)->get(route('chat.download', $message))->assertOk();
        $this->actingAs($client)->get(route('chat.preview', $message))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_geen_voorbeeld_voor_zip(): void
    {
        $client = $this->client();
        $message = $this->fileMessage($client, $this->admin(), 'local', 'chat-files/test.zip', 'bestanden.zip');

        $this->actingAs($client)->get(route('chat.preview', $message))->assertNotFound();
        $this->actingAs($client)->get(route('chat.download', $message))->assertOk();
    }

    public function test_oude_bijlage_op_public_werkt_nog(): void
    {
        $client = $this->client();
        $message = $this->fileMessage($client, $this->admin(), 'public');

        $this->actingAs($client)->get(route('chat.download', $message))->assertOk();
    }

    public function test_nieuwe_bijlage_komt_op_de_priveschijf(): void
    {
        $client = $this->client();
        $this->admin();

        $this->actingAs($client)->post(route('chat.store'), [
            'files' => [UploadedFile::fake()->create('offerte.pdf', 20, 'application/pdf')],
        ])->assertSessionHasNoErrors();

        $message = Message::first();
        Storage::disk('local')->assertExists($message->file_path);
        Storage::disk('public')->assertMissing($message->file_path);
    }

    public function test_chatpagina_bevat_geen_directe_storage_links(): void
    {
        $client = $this->client();
        $this->fileMessage($this->admin(), $client);

        $this->actingAs($client)->get(route('chat.index'))
            ->assertOk()
            ->assertDontSee('/storage/chat-files', false);
    }

    public function test_bestandsnaam_met_code_wordt_nooit_als_code_in_de_pagina_gezet(): void
    {
        $client = $this->client();
        $admin = $this->admin();
        $this->fileMessage($client, $admin, 'local', 'chat-files/test.pdf', "a');alert(1);//.pdf");

        $this->actingAs($admin)->get(route('chat.index', ['client_id' => $client->id]))
            ->assertOk()
            ->assertDontSee("a');alert(1)", false);
    }

    public function test_klant_kan_geen_andere_klant_berichten(): void
    {
        $admin = $this->admin();
        $client = $this->client();
        $other = $this->client();

        // Ook al stuurt de klant een andere receiver_id mee, het bericht gaat naar GKR
        $this->actingAs($client)->post(route('chat.store'), [
            'receiver_id' => $other->id,
            'body'        => 'Hallo',
        ]);

        $this->assertSame($admin->id, (int) Message::first()->receiver_id);
    }

    public function test_admin_kan_alleen_naar_een_klant_sturen(): void
    {
        $admin = $this->admin();
        $otherAdmin = $this->admin();

        $this->actingAs($admin)->post(route('chat.store'), [
            'receiver_id' => $otherAdmin->id,
            'body'        => 'Hallo',
        ])->assertSessionHasErrors('receiver_id');

        $this->assertDatabaseCount('messages', 0);
    }
}