<?php

namespace Tests\Feature;

use App\Models\AdAccount;
use App\Models\User;
use App\Services\MetaAdsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetaAdsPagesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function client(?string $accountId = null): User
    {
        $client = User::factory()->create(['is_admin' => false]);

        if ($accountId) {
            $client->adAccounts()->create([
                'platform'   => AdAccount::PLATFORM_META,
                'account_id' => $accountId,
            ]);
        }

        return $client;
    }

    private function impressionsFor(User $client): string
    {
        $report = app(MetaAdsService::class)->getInsights($client->fresh()->metaAdAccount, 28);

        return number_format($report['totals']['impressions'], 0, ',', '.');
    }

    public function test_klant_ziet_eigen_cijfers(): void
    {
        $client = $this->client('111111111');

        $this->actingAs($client)->get(route('meta-ads.index'))
            ->assertOk()
            ->assertSee('Besteed budget')
            ->assertSee($this->impressionsFor($client));
    }

    public function test_klant_ziet_nooit_cijfers_van_andere_klant(): void
    {
        $clientA = $this->client('111111111');
        $clientB = $this->client('222222222');

        $this->actingAs($clientA)->get(route('meta-ads.index'))
            ->assertOk()
            ->assertDontSee($this->impressionsFor($clientB));
    }

    public function test_klant_zonder_koppeling_krijgt_nette_melding(): void
    {
        $this->actingAs($this->client())->get(route('meta-ads.index'))
            ->assertOk()
            ->assertSee('nog geen advertentiecijfers');
    }

    public function test_gast_wordt_naar_login_gestuurd(): void
    {
        $this->get(route('meta-ads.index'))->assertRedirect(route('login'));
    }

    public function test_klant_kan_adminpaginas_niet_openen(): void
    {
        $client = $this->client('111111111');
        $other = $this->client('222222222');

        $this->assertNotEquals(200, $this->actingAs($client)->get(route('admin.meta-ads.index'))->status());
        $this->assertNotEquals(200, $this->actingAs($client)->get(route('admin.meta-ads.show', $other))->status());
    }

    public function test_admin_ziet_overzicht_van_gekoppelde_klanten(): void
    {
        $client = $this->client('111111111');

        $this->actingAs($this->admin())->get(route('admin.meta-ads.index'))
            ->assertOk()
            ->assertSee($client->name)
            ->assertSee($this->impressionsFor($client));
    }

    public function test_admin_detail_van_klant_zonder_koppeling_geeft_404(): void
    {
        $this->actingAs($this->admin())->get(route('admin.meta-ads.show', $this->client()))
            ->assertNotFound();
    }

    public function test_admin_detail_van_een_admin_geeft_404(): void
    {
        $this->actingAs($this->admin())->get(route('admin.meta-ads.show', $this->admin()))
            ->assertNotFound();
    }

    public function test_fout_bij_meta_wordt_netjes_afgevangen(): void
    {
        $client = $this->client('111111111');

        $this->mock(MetaAdsService::class, function ($mock) {
            $mock->shouldReceive('getInsights')->andThrow(new \RuntimeException('Meta is onbereikbaar'));
        });

        $this->actingAs($client)->get(route('meta-ads.index'))
            ->assertOk()
            ->assertSee('kunnen op dit moment niet worden geladen')
            ->assertDontSee('Meta is onbereikbaar');
    }

    public function test_onbekende_periode_valt_terug_op_28_dagen(): void
    {
        $client = $this->client('111111111');

        $this->actingAs($client)->get(route('meta-ads.index', ['days' => 50]))
            ->assertOk()
            ->assertSee($this->impressionsFor($client));
    }


    public function test_klant_ziet_uitleg_bij_cijfers(): void
{
    $client = $this->client('111111111');

    $this->actingAs($client)->get(route('meta-ads.index'))
        ->assertOk()
        ->assertSee('meerdere keren zien');
}

public function test_cijferkaarten_zijn_klikbaar(): void
{
    $client = $this->client('111111111');

    $this->actingAs($client)->get(route('meta-ads.index'))
        ->assertOk()
        ->assertSee('data-metric="impressions"', false)
        ->assertSee('data-metric="conversions"', false);
}

    // ---------- Campagnes (nieuw na het gesprek met Stijn, 7 okt 2026) ----------

    private function reportFor(User $client): array
    {
        return app(MetaAdsService::class)->getInsights($client->fresh()->metaAdAccount, 28);
    }

    public function test_klant_ziet_campagnes_van_het_eigen_account(): void
    {
        $client = $this->client('111111111');
        $campaignName = $this->reportFor($client)['campaigns'][0]['name'];

        $this->actingAs($client)->get(route('meta-ads.index'))
            ->assertOk()
            ->assertSee('Per campagne')
            ->assertSee($campaignName)
            ->assertDontSee('Alleen zichtbaar voor GKR');
    }

    public function test_admin_ziet_campagnes_op_detailpagina(): void
    {
        $client = $this->client('111111111');
        $campaignName = $this->reportFor($client)['campaigns'][0]['name'];

        $this->actingAs($this->admin())->get(route('admin.meta-ads.show', $client))
            ->assertOk()
            ->assertSee('Per campagne')
            ->assertSee($campaignName);
    }

    public function test_campagnenaam_wordt_nooit_als_code_uitgevoerd(): void
    {
        // Een campagnenaam komt van buiten (Meta). Staat er HTML in, dan moet die als gewone tekst verschijnen.
        $client = $this->client('111111111');
        $report = $this->reportFor($client);
        $report['campaigns'][0]['name'] = '<script>alert("campagne")</script>';

        $this->mock(MetaAdsService::class, function ($mock) use ($report) {
            $mock->shouldReceive('getInsights')->andReturn($report);
        });

        $this->actingAs($client)->get(route('meta-ads.index'))
            ->assertOk()
            ->assertDontSee('<script>alert("campagne")</script>', false)
            ->assertSee('&lt;script&gt;alert(', false);
    }

    public function test_rapport_zonder_campagnes_toont_geen_lege_tabel(): void
    {
        // Bijvoorbeeld een account zonder lopende campagnes in deze periode
        $client = $this->client('111111111');
        $report = $this->reportFor($client);
        $report['campaigns'] = [];

        $this->mock(MetaAdsService::class, function ($mock) use ($report) {
            $mock->shouldReceive('getInsights')->andReturn($report);
        });

        $this->actingAs($client)->get(route('meta-ads.index'))
            ->assertOk()
            ->assertSee('Besteed budget')
            ->assertDontSee('Per campagne');
    }
}