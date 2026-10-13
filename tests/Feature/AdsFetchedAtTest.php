<?php

namespace Tests\Feature;

use App\Models\AdAccount;
use App\Models\User;
use App\Services\GoogleAdsService;
use App\Services\MetaAdsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Test "Opgehaald om ..." op de pagina's met advertentiecijfers (Google Ads en Meta),
 * net als op de pagina van Google Analytics.
 */
class AdsFetchedAtTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Nepdata: er wordt niets bij Google of Meta opgevraagd
        config([
            'services.google_ads.fake' => true,
            'services.meta_ads.fake'   => true,
        ]);

        $this->travelTo(Carbon::parse('2026-10-13 10:05:00', 'Europe/Amsterdam'));
    }

    private function client(string $platform, string $accountId): User
    {
        $client = User::factory()->create(['is_admin' => false]);
        $client->adAccounts()->create(['platform' => $platform, 'account_id' => $accountId]);

        return $client->fresh();
    }

    // ---------- 1. Het rapport onthoudt wanneer het is opgehaald ----------

    public function test_google_ads_rapport_bevat_het_tijdstip_van_ophalen(): void
    {
        $client = $this->client(AdAccount::PLATFORM_GOOGLE_ADS, '1111111111');

        $report = app(GoogleAdsService::class)->getInsights($client->googleAdsAccount, 28);

        $this->assertTrue(Carbon::parse($report['fetched_at'])->equalTo(now()));
    }

    public function test_meta_rapport_bevat_het_tijdstip_van_ophalen(): void
    {
        $client = $this->client(AdAccount::PLATFORM_META, 'act_1234567890');

        $report = app(MetaAdsService::class)->getInsights($client->metaAdAccount, 28);

        $this->assertTrue(Carbon::parse($report['fetched_at'])->equalTo(now()));
    }

    public function test_uit_de_cache_blijft_het_oorspronkelijke_tijdstip_staan(): void
    {
        $client = $this->client(AdAccount::PLATFORM_GOOGLE_ADS, '1111111111');
        $service = app(GoogleAdsService::class);

        $first = $service->getInsights($client->googleAdsAccount, 28);

        // Een uur later komt het rapport uit de cache: het tijdstip verandert niet
        $this->travel(1)->hours();
        $second = $service->getInsights($client->googleAdsAccount, 28);

        $this->assertSame($first['fetched_at'], $second['fetched_at']);
    }

    // ---------- 2. De pagina's tonen het ----------

    public function test_klant_ziet_wanneer_de_google_ads_cijfers_zijn_opgehaald(): void
    {
        $client = $this->client(AdAccount::PLATFORM_GOOGLE_ADS, '1111111111');

        $this->actingAs($client)->get(route('google-ads.index'))
            ->assertOk()
            ->assertSee('Opgehaald om 10:05');
    }

    public function test_klant_ziet_wanneer_de_meta_cijfers_zijn_opgehaald(): void
    {
        $client = $this->client(AdAccount::PLATFORM_META, 'act_1234567890');

        $this->actingAs($client)->get(route('meta-ads.index'))
            ->assertOk()
            ->assertSee('Opgehaald om 10:05');
    }

    public function test_medewerker_ziet_het_op_de_detailpagina_van_een_klant(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $google = $this->client(AdAccount::PLATFORM_GOOGLE_ADS, '1111111111');
        $meta = $this->client(AdAccount::PLATFORM_META, 'act_1234567890');

        $this->actingAs($admin)->get(route('admin.google-ads.show', $google))
            ->assertOk()
            ->assertSee('Opgehaald om 10:05');

        $this->actingAs($admin)->get(route('admin.meta-ads.show', $meta))
            ->assertOk()
            ->assertSee('Opgehaald om 10:05');
    }
}