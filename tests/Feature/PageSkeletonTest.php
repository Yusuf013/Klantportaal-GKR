<?php

namespace Tests\Feature;

use App\Models\AdAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test het "skelet": de grijze blokken die je even ziet als een pagina met marketingcijfers
 * nog aan het laden is. Het skelet staat verborgen in de layout van elke pagina achter de
 * inlog; een script toont het zodra je op zo'n pagina klikt en het laden even duurt.
 *
 * (Het gedrag van het script zelf is in een echte browser getest; hier controleren we dat
 * de onderdelen op de pagina staan en dat de gewone pagina's blijven werken.)
 */
class PageSkeletonTest extends TestCase
{
    use RefreshDatabase;

    private function client(): User
    {
        return User::factory()->create(['is_admin' => false]);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_pagina_achter_de_inlog_heeft_het_verborgen_skelet_en_het_script(): void
    {
        $this->actingAs($this->client())->get(route('profile.edit'))
            ->assertOk()
            // "hidden": het skelet is er, maar je ziet het niet zolang er niets laadt
            ->assertSee('id="page-skeleton" hidden', false)
            ->assertSee('id="page-main"', false)
            ->assertSee('id="page-skeleton-script"', false);
    }

    public function test_medewerker_heeft_het_skelet_ook(): void
    {
        $this->actingAs($this->admin())->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('id="page-skeleton" hidden', false)
            ->assertSee('id="page-skeleton-script"', false);
    }

    public function test_inlogpagina_heeft_geen_skelet(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('page-skeleton', false);
    }

    public function test_marketingpagina_zelf_toont_gewoon_de_cijfers(): void
    {
        // Met nepdata (standaard in de tests) gaat er niets naar Google
        $client = $this->client();
        $client->adAccounts()->create(['platform' => AdAccount::PLATFORM_GOOGLE_ADS, 'account_id' => '1111111111']);

        $this->actingAs($client)->get(route('google-ads.index'))
            ->assertOk()
            ->assertSee('Google Ads-resultaten')
            // De kop en de inhoud hebben een naam gekregen, zodat het script ze kan verbergen
            ->assertSee('id="page-header"', false)
            ->assertSee('id="page-main"', false)
            ->assertSee('id="page-skeleton" hidden', false);
    }

    public function test_het_skelet_bevat_geen_gegevens_van_klanten(): void
    {
        User::factory()->create(['is_admin' => false, 'name' => 'Anna Achternaam', 'email' => 'anna@example.com']);

        // Gebruikersbeheer: een pagina waar de gegevens van klanten wel op staan
        $content = $this->actingAs($this->admin())->get(route('admin.users.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Anna Achternaam', $content);

        // Alles vanaf het skelet tot het einde van de pagina (het skelet en de scripts van de layout)
        $tail = substr($content, strpos($content, 'id="page-skeleton"'));

        $this->assertStringNotContainsString('Anna Achternaam', $tail);
        $this->assertStringNotContainsString('anna@example.com', $tail);
    }
}