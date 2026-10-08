<?php

namespace Tests\Feature\Api;

use App\Models\Document;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Walking-skeleton-tests voor de nieuwe API-laag: Sanctum-authenticatie (ADR-003) en
 * user_id-scoping op het ene nieuwe endpoint (zie plan "Infrastructuur, testen en API-laag
 * vóór de iOS-basis"). Dit is nog niet de volledige cross-klant regressiesuite (NFR-01/02) —
 * die volgt zodra het Klant-model/KlantScope (ADR-001) er is.
 */
class DocumentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_with_correct_credentials_returns_a_token(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'is_admin']])
            ->assertJsonPath('user.is_admin', false);
    }

    public function test_login_response_reports_admin_role(): void
    {
        $admin = User::factory()->admin()->create(['password' => bcrypt('secret123')]);

        $this->postJson('/api/login', ['email' => $admin->email, 'password' => 'secret123'])
            ->assertOk()
            ->assertJsonPath('user.is_admin', true);
    }

    public function test_login_with_incorrect_credentials_is_rejected(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable();
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mobile')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/logout')
            ->assertOk();

        $this->assertSame(0, $user->tokens()->count());

        // De sanctum-guard cachet de opgeloste gebruiker binnen één testrun (niet in productie,
        // waar elk HTTP-request een eigen lifecycle heeft) — expliciet vergeten om een echte,
        // onafhankelijke tweede request te simuleren.
        Auth::forgetGuards();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/documenten')
            ->assertUnauthorized();
    }

    public function test_unauthenticated_request_to_documenten_is_rejected(): void
    {
        $this->getJson('/api/documenten')->assertUnauthorized();
    }

    public function test_user_only_sees_documents_from_their_own_projects(): void
    {
        $ownUser = User::factory()->create();
        $ownProject = Project::create(['user_id' => $ownUser->id, 'name' => 'Eigen project']);
        Document::create(['project_id' => $ownProject->id, 'name' => 'eigen-document.pdf', 'file_path' => 'docs/eigen-document.pdf']);

        $otherUser = User::factory()->create();
        $otherProject = Project::create(['user_id' => $otherUser->id, 'name' => 'Ander project']);
        Document::create(['project_id' => $otherProject->id, 'name' => 'ander-document.pdf', 'file_path' => 'docs/ander-document.pdf']);

        Sanctum::actingAs($ownUser);

        $response = $this->getJson('/api/documenten');

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['name' => 'eigen-document.pdf'])
            ->assertJsonMissing(['name' => 'ander-document.pdf']);
    }
}
