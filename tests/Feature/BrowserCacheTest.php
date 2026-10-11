<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test dat de browser pagina's achter de inlog niet bewaart.
 * Anders kan iemand na het uitloggen met de terugknop zien wat de vorige gebruiker bekeek.
 */
class BrowserCacheTest extends TestCase
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

    private function cacheControl($response): string
    {
        return (string) $response->headers->get('Cache-Control');
    }

    public function test_pagina_van_een_ingelogde_klant_wordt_niet_bewaard(): void
    {
        $response = $this->actingAs($this->client())->get(route('chat.index'))->assertOk();

        $this->assertStringContainsString('no-store', $this->cacheControl($response));
        $this->assertStringContainsString('private', $this->cacheControl($response));
    }

    public function test_pagina_van_een_ingelogde_medewerker_wordt_niet_bewaard(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.users.index'))->assertOk();

        $this->assertStringContainsString('no-store', $this->cacheControl($response));
    }

    public function test_ook_een_doorverwijzing_voor_een_ingelogde_gebruiker_wordt_niet_bewaard(): void
    {
        // Een klant die een adminpagina opvraagt, wordt geweigerd of doorgestuurd.
        // Ook dat antwoord mag de browser niet bewaren.
        $response = $this->actingAs($this->client())->get(route('admin.users.index'));

        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertStringContainsString('no-store', $this->cacheControl($response));
    }

    public function test_inlogpagina_voor_gasten_valt_hier_buiten(): void
    {
        $response = $this->get(route('login'))->assertOk();

        $this->assertStringNotContainsString('no-store', $this->cacheControl($response));
    }

    public function test_na_uitloggen_stuurt_een_beschermde_pagina_naar_de_inlog(): void
    {
        $client = $this->client();

        $this->actingAs($client)->get(route('chat.index'))->assertOk();
        $this->actingAs($client)->post(route('logout'));

        // Dit is wat de terugknop nu doet: de pagina opnieuw opvragen bij de server
        $this->get(route('chat.index'))->assertRedirect(route('login'));
    }

    public function test_pagina_herlaadt_zichzelf_als_de_browser_hem_toch_uit_het_geheugen_haalt(): void
    {
        $this->actingAs($this->client())->get(route('chat.index'))
            ->assertOk()
            ->assertSee('event.persisted', false)
            ->assertSee('window.location.reload()', false);
    }
}