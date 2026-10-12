<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test de startpagina van het klantportaal.
 */
class WelcomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_bezoeker_ziet_de_startpagina_met_een_knop_om_in_te_loggen(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Je project bij GKR, overzichtelijk op één plek.')
            ->assertSee('href="' . route('login') . '"', false)
            ->assertSee('Nog geen account?')
            ->assertSee('https://gkr.nl/', false);
    }

    public function test_er_is_geen_knop_om_zelf_te_registreren(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertDontSee('/register', false)
            ->assertDontSee('Aanmelden');
    }

    public function test_geen_teksten_meer_over_de_stage(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertDontSee('Stageproject')
            ->assertDontSee('Yusuf');
    }

    public function test_ingelogde_gebruiker_krijgt_een_knop_naar_het_dashboard(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))->get('/')
            ->assertOk()
            ->assertSee('Naar je dashboard')
            ->assertSee('href="' . route('dashboard') . '"', false);
    }
}