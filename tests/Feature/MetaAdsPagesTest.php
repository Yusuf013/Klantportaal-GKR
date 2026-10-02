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
}