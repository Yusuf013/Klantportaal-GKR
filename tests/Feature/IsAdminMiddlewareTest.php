<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * De `admin`-middleware wordt gedeeld door het web-adminpaneel en de API (issue #32). Het
 * webgedrag (redirect) mag niet veranderen; de API moet een echte 403 geven, geen redirect.
 */
class IsAdminMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['auth:sanctum', 'admin'])
            ->get('/api/_test/admin-only', fn () => response()->json(['ok' => true]));
    }

    public function test_non_admin_web_request_is_still_redirected_to_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin/dashboard')
            ->assertRedirect('/dashboard');
    }

    public function test_non_admin_api_request_gets_json_403_instead_of_redirect(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/_test/admin-only')
            ->assertStatus(403)
            ->assertExactJson(['message' => 'Geen adminrechten.']);
    }

    public function test_admin_api_request_passes(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/_test/admin-only')->assertOk();
    }
}
