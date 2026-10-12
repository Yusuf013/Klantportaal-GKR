<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Zelf registreren kan niet meer: GKR maakt de accounts van klanten aan.
 * (Hoe dat werkt staat in InvitationTest.)
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registratiepagina_bestaat_niet_meer(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_zelf_een_account_aanmaken_kan_niet(): void
    {
        $this->post('/register', [
            'name'                  => 'Test User',
            'email'                 => 'test@example.com',
            'password'              => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $this->assertNull(User::where('email', 'test@example.com')->first());
    }
}