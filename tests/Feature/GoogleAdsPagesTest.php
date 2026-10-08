<?php

namespace Tests\Feature;

use App\Models\AdAccount;
use App\Models\User;
use App\Services\GoogleAdsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleAdsPagesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function client(?string $customerId = null): User
    {
        $client = User::factory()->create(['is_admin' => false]);

        if ($customerId) {
            $client->adAccounts()->create([
                'platform'   => AdAccount::PLATFORM_GOOGLE_ADS,
                'account_id' => $customerId,
            ]);
        }

        return $client;
    }

    private function report(User $client): array
    {
        return app(GoogleAdsService::class)->getInsights($client->fresh()->googleAdsAccount, 28);
    }

    private function impressionsFor(User $client): string
    {
        return number_format($this->report($client)['totals']['impressions'], 0, ',', '.');
    }

    public function test_klant_ziet_eigen_cijfers(): void
    {
        $client = $this->client('1111111111');

        $this->actingAs($client)->get(route('google-ads.index'))
            ->assertOk()
            ->assertSee('Kosten')
            ->assertSee($this->impressionsFor($client));
    }

    public function test_klant_ziet_nooit_cijfers_van_andere_klant(): void
    {
        $clientA = $this->client('1111111111');
        $clientB = $this->client('2222222222');

        $this->actingAs($clientA)->get(route('google-ads.index'))
            ->assertOk()
            ->assertDontSee($this->impressionsFor($clientB));
    }

    public function test_klant_zonder_koppeling_krijgt_nette_melding(): void
    {
        $this->actingAs($this->client())->get(route('google-ads.index'))
            ->assertOk()
            ->assertSee('nog geen advertentiecijfers');
    }

    public function test_gast_wordt_naar_login_gestuurd(): void
    {
        $this->get(route('google-ads.index'))->assertRedirect(route('login'));
    }

    public function test_klant_kan_adminpaginas_niet_openen(): void
    {
        $client = $this->client('1111111111');
        $other = $this->client('2222222222');

        $this->assertNotEquals(200, $this->actingAs($client)->get(route('admin.google-ads.index'))->status());
        $this->assertNotEquals(200, $this->actingAs($client)->get(route('admin.google-ads.show', $other))->status());
    }

    // Was: test_klant_ziet_geen_campagnes. Sinds het gesprek met Stijn (7 okt 2026)
    // mogen klanten de campagnes van hun eigen account zien.
    public function test_klant_ziet_campagnes_van_het_eigen_account(): void
    {
        $client = $this->client('1111111111');
        $campaignName = $this->report($client)['campaigns'][0]['name'];

        $this->actingAs($client)->get(route('google-ads.index'))
            ->assertOk()
            ->assertSee('Per campagne')
            ->assertSee($campaignName)
            ->assertDontSee('Alleen zichtbaar voor GKR');
    }

    public function test_admin_ziet_overzicht_van_gekoppelde_klanten(): void
    {
        $client = $this->client('1111111111');

        $this->actingAs($this->admin())->get(route('admin.google-ads.index'))
            ->assertOk()
            ->assertSee($client->name)
            ->assertSee($this->impressionsFor($client));
    }

    public function test_admin_ziet_campagnes_op_detailpagina(): void
    {
        $client = $this->client('1111111111');
        $campaignName = $this->report($client)['campaigns'][0]['name'];

        $this->actingAs($this->admin())->get(route('admin.google-ads.show', $client))
            ->assertOk()
            ->assertSee('Per campagne')
            ->assertSee($campaignName);
    }

    public function test_admin_detail_van_klant_zonder_koppeling_geeft_404(): void
    {
        $this->actingAs($this->admin())->get(route('admin.google-ads.show', $this->client()))
            ->assertNotFound();
    }

    public function test_admin_detail_van_een_admin_geeft_404(): void
    {
        $this->actingAs($this->admin())->get(route('admin.google-ads.show', $this->admin()))
            ->assertNotFound();
    }

    public function test_fout_bij_google_wordt_netjes_afgevangen(): void
    {
        $client = $this->client('1111111111');

        $this->mock(GoogleAdsService::class, function ($mock) {
            $mock->shouldReceive('getInsights')->andThrow(new \RuntimeException('Google is onbereikbaar'));
        });

        $this->actingAs($client)->get(route('google-ads.index'))
            ->assertOk()
            ->assertSee('kunnen op dit moment niet worden geladen')
            ->assertDontSee('Google is onbereikbaar');
    }

    public function test_onbekende_periode_valt_terug_op_28_dagen(): void
    {
        $client = $this->client('1111111111');

        $this->actingAs($client)->get(route('google-ads.index', ['days' => 50]))
            ->assertOk()
            ->assertSee($this->impressionsFor($client));
    }

    public function test_klant_ziet_uitleg_voor_google(): void
    {
        $client = $this->client('1111111111');

        $this->actingAs($client)->get(route('google-ads.index'))
            ->assertOk()
            ->assertSee('op Google, bijvoorbeeld in de zoekresultaten')
            ->assertDontSee('Facebook en Instagram');
    }

    public function test_sidebar_toont_google_ads_alleen_bij_koppeling(): void
    {
        $metaOnly = User::factory()->create(['is_admin' => false]);
        $metaOnly->adAccounts()->create(['platform' => AdAccount::PLATFORM_META, 'account_id' => '111111111']);

        $both = $this->client('1111111111');
        $both->adAccounts()->create(['platform' => AdAccount::PLATFORM_META, 'account_id' => '222222222']);

        // Op de Meta-pagina komt een link naar Google Ads alleen uit de sidebar
        $this->actingAs($metaOnly)->get(route('meta-ads.index'))
            ->assertOk()
            ->assertDontSee(route('google-ads.index'));

        $this->actingAs($both)->get(route('meta-ads.index'))
            ->assertOk()
            ->assertSee(route('google-ads.index'));
    }
}