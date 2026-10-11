<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Document;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test wie een reactie mag plaatsen bij een projectdocument: alleen GKR en de klant van
 * dat project. Voorheen kon elke ingelogde klant reageren op het document van een ander,
 * door het nummer in de URL te veranderen.
 */
class CommentSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function client(): User
    {
        return User::factory()->create(['is_admin' => false]);
    }

    private function documentOf(User $client): Document
    {
        $project = Project::forceCreate(['user_id' => $client->id, 'name' => 'Nieuwe website']);

        return Document::create(['project_id' => $project->id, 'name' => 'Offerte', 'file_path' => 'documents/test.pdf', 'status' => 'Verzonden']);
    }

    public function test_gast_wordt_naar_login_gestuurd(): void
    {
        $document = $this->documentOf($this->client());

        $this->post(route('comments.store', $document), ['content' => 'Reactie'])->assertRedirect(route('login'));

        $this->assertDatabaseCount('comments', 0);
    }

    public function test_klant_kan_reageren_op_een_eigen_document(): void
    {
        $client = $this->client();
        $document = $this->documentOf($client);

        $this->actingAs($client)->post(route('comments.store', $document), ['content' => 'Graag het logo groter'])
            ->assertSessionHasNoErrors();

        $comment = Comment::firstOrFail();
        $this->assertSame('Graag het logo groter', $comment->content);
        $this->assertSame($client->id, (int) $comment->user_id);
        $this->assertSame($document->id, (int) $comment->document_id);
    }

    public function test_klant_kan_niet_reageren_op_het_document_van_een_ander(): void
    {
        $document = $this->documentOf($this->client());

        $this->actingAs($this->client())->post(route('comments.store', $document), ['content' => 'Dit hoort hier niet'])
            ->assertNotFound();

        $this->assertDatabaseCount('comments', 0);
    }

    public function test_medewerker_kan_reageren_op_elk_document(): void
    {
        $admin = $this->admin();
        $document = $this->documentOf($this->client());

        $this->actingAs($admin)->post(route('comments.store', $document), ['content' => 'Wij passen het aan'])
            ->assertSessionHasNoErrors();

        $this->assertSame($admin->id, (int) Comment::firstOrFail()->user_id);
    }

    public function test_lege_reactie_wordt_nog_steeds_geweigerd(): void
    {
        $client = $this->client();
        $document = $this->documentOf($client);

        $this->actingAs($client)->post(route('comments.store', $document), ['content' => ''])
            ->assertSessionHasErrors('content');

        $this->assertDatabaseCount('comments', 0);
    }
}