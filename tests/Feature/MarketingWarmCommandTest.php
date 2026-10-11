<?php

namespace Tests\Feature;

use App\Models\AdAccount;
use App\Models\User;
use App\Services\GoogleAccessToken;
use App\Services\GoogleAdsService;
use App\Services\GoogleAnalyticsService;
use App\Services\MetaAdsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * Test het commando marketing:warm. Dat haalt de marketingcijfers op de achtergrond op,
 * zodat een bezoeker niet hoeft te wachten tot Google of Meta antwoord geeft.
 *
 * Er gaat nooit een echt verzoek naar buiten: Http::fake() doet alsof Google Ads en Meta
 * antwoorden, en Google Analytics wordt nagebootst (mock).
 *
 * Per rapport doet Google Ads 2 verzoeken (deze periode en de vorige) en Meta 3
 * (deze periode, de vorige en de campagnes). Er zijn 4 periodes: 7, 28, 90 en 365 dagen.
 */
class MarketingWarmCommandTest extends TestCase
{
    use RefreshDatabase;

    // ---------- Hulpfuncties ----------

    protected function setUp(): void
    {
        parent::setUp();

        // Vaste datum: het is 10 oktober 2026, dus "gisteren" is 9 oktober
        $this->travelTo(Carbon::parse('2026-10-10 12:00:00'));

        config([
            'services.google_ads.api_version'           => 'v25',
            'services.google_ads.login_customer_id'     => null,
            'services.meta_ads.access_token'            => 'test-token',
            'services.meta_ads.api_version'             => 'v26.0',
            // Google Analytics staat in deze tests uit, behalve waar we de service nabootsen.
            // Zo kan een test nooit per ongeluk de echte Analytics van GKR aanroepen.
            'services.google_analytics.property_id'     => null,
        ]);
        Cache::flush();

        // Het inloggen van het robotaccount is vervangen door een nepversie
        $this->mock(GoogleAccessToken::class, function ($mock) {
            $mock->shouldReceive('forScope')->andReturn('test-token');
        });
    }

    /**
     * Een klant met een gekoppeld advertentieaccount.
     */
    private function clientWith(string $platform, string $accountId): AdAccount
    {
        $client = User::factory()->create(['is_admin' => false]);

        return $client->adAccounts()->create(['platform' => $platform, 'account_id' => $accountId]);
    }

    private function googleAccount(string $customerId): AdAccount
    {
        return $this->clientWith(AdAccount::PLATFORM_GOOGLE_ADS, $customerId);
    }

    /**
     * Eén rij zoals Google Ads die teruggeeft, op 9 oktober 2026 (gisteren).
     */
    private function googleRow(int $impressions): array
    {
        return [
            'campaign' => ['id' => '111', 'name' => 'Zoeken - Merknaam'],
            'metrics'  => ['impressions' => (string) $impressions, 'clicks' => '10', 'conversions' => 1.0, 'costMicros' => '1000000'],
            'segments' => ['date' => '2026-10-09'],
        ];
    }

    private function useRealGoogleAds(): void
    {
        config(['services.google_ads.fake' => false]);
    }

    private function useRealMeta(): void
    {
        config(['services.meta_ads.fake' => false]);
    }

    // ---------- 1. Nepdata ----------

    public function test_met_nepdata_gaat_er_niets_naar_google_of_meta(): void
    {
        // Standaard in de tests: beide koppelingen staan op nepdata
        Http::fake();
        $this->googleAccount('1111111111');
        $this->clientWith(AdAccount::PLATFORM_META, '123456789');

        $this->artisan('marketing:warm')
            ->expectsOutputToContain('Google Ads: staat op nepdata')
            ->expectsOutputToContain('Meta: staat op nepdata')
            ->expectsOutputToContain('Google Analytics: niet ingesteld')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    // ---------- 2. Google Ads ----------

    public function test_google_ads_wordt_voor_alle_accounts_en_periodes_klaargezet(): void
    {
        $this->useRealGoogleAds();
        Http::fake(fn () => Http::response(['results' => [$this->googleRow(1000)]]));

        $anna = $this->googleAccount('1111111111');
        $bram = $this->googleAccount('2222222222');

        $this->artisan('marketing:warm')
            ->expectsOutputToContain('Google Ads: 8 rapporten klaargezet, 0 accounts mislukt (van 2)')
            ->assertSuccessful();

        // 2 accounts x 4 periodes x 2 verzoeken
        Http::assertSentCount(16);

        // Daarna ligt alles klaar: opvragen kost geen enkel verzoek meer
        $service = app(GoogleAdsService::class);

        foreach ([$anna, $bram] as $account) {
            foreach (GoogleAdsService::PERIODS as $days) {
                $this->assertSame(1000, $service->getInsights($account, $days)['totals']['impressions']);
            }
        }

        Http::assertSentCount(16);
    }

    public function test_klant_hoeft_daarna_niet_meer_te_wachten_op_google(): void
    {
        $this->useRealGoogleAds();
        Http::fake(fn () => Http::response(['results' => [$this->googleRow(1000)]]));

        $account = $this->googleAccount('1111111111');

        $this->artisan('marketing:warm')->assertSuccessful();
        Http::assertSentCount(8);

        // De klant opent de pagina: er gaat niets meer naar Google
        $this->actingAs($account->user)->get(route('google-ads.index'))->assertOk();

        Http::assertSentCount(8);
    }

    public function test_een_bestaand_rapport_wordt_vervangen_door_een_vers_rapport(): void
    {
        $this->useRealGoogleAds();

        // Google geeft elke keer de cijfers die op dat moment in $impressions staan
        $impressions = 1000;
        Http::fake(function () use (&$impressions) {
            return Http::response(['results' => [$this->googleRow($impressions)]]);
        });

        $account = $this->googleAccount('1111111111');
        $service = app(GoogleAdsService::class);

        $this->assertSame(1000, $service->getInsights($account, 28)['totals']['impressions']);

        // Bij Google zijn de cijfers bijgewerkt; het commando haalt ze opnieuw op
        $impressions = 1500;
        $this->artisan('marketing:warm')->assertSuccessful();

        $this->assertSame(1500, $service->getInsights($account, 28)['totals']['impressions']);
    }

    public function test_een_mislukt_account_houdt_de_andere_accounts_niet_tegen(): void
    {
        $this->useRealGoogleAds();

        // Het eerste account geeft "geen toegang", het tweede werkt gewoon
        Http::fake(function ($request) {
            return str_contains($request->url(), 'customers/1111111111/')
                ? Http::response(['error' => ['code' => 403, 'status' => 'PERMISSION_DENIED']], 403)
                : Http::response(['results' => [$this->googleRow(1000)]]);
        });

        $broken = $this->googleAccount('1111111111');
        $working = $this->googleAccount('2222222222');

        // Het commando zelf slaagt: één kapotte koppeling mag de rest niet stilleggen
        $this->artisan('marketing:warm')
            ->expectsOutputToContain('Google Ads: 4 rapporten klaargezet, 1 accounts mislukt (van 2)')
            ->assertSuccessful();

        // Kapot account: 1 verzoek en dan gestopt (de andere periodes zijn overgeslagen).
        // Werkend account: 4 periodes x 2 verzoeken.
        Http::assertSentCount(9);

        $service = app(GoogleAdsService::class);

        // Het werkende account ligt klaar
        $this->assertSame(1000, $service->getInsights($working, 28)['totals']['impressions']);

        // Het kapotte account: de pagina wacht niet opnieuw op Google, maar meldt direct dat het misging
        try {
            $service->getInsights($broken, 7);
            $this->fail('Er had een fout moeten komen.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('zojuist mislukt', $e->getMessage());
        }

        Http::assertSentCount(9);
    }

    // ---------- 3. Meta ----------

    public function test_meta_wordt_voor_elke_periode_klaargezet(): void
    {
        $this->useRealMeta();
        Http::fake(fn () => Http::response(['data' => []]));

        $account = $this->clientWith(AdAccount::PLATFORM_META, '123456789');

        $this->artisan('marketing:warm')
            ->expectsOutputToContain('Meta: 4 rapporten klaargezet, 0 accounts mislukt (van 1)')
            ->assertSuccessful();

        // 1 account x 4 periodes x 3 verzoeken
        Http::assertSentCount(12);

        // Daarna ligt alles klaar
        $report = app(MetaAdsService::class)->getInsights($account, 28);

        $this->assertFalse($report['is_fake']);
        Http::assertSentCount(12);
    }

    public function test_het_commando_haalt_alleen_het_platform_op_dat_echt_aan_staat(): void
    {
        // Google Ads echt, Meta nog op nepdata (zoals nu op Railway)
        $this->useRealGoogleAds();
        Http::fake(fn () => Http::response(['results' => []]));

        $this->googleAccount('1111111111');
        $this->clientWith(AdAccount::PLATFORM_META, '123456789');

        $this->artisan('marketing:warm')->assertSuccessful();

        Http::assertSentCount(8);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'facebook.com'));
    }

    // ---------- 4. Google Analytics ----------

    public function test_google_analytics_wordt_voor_elke_periode_klaargezet(): void
    {
        Http::fake();

        $this->mock(GoogleAnalyticsService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);

            foreach ([7, 28, 90, 365] as $days) {
                $mock->shouldReceive('refreshDashboard')->once()->with($days)->andReturn([]);
            }
        });

        $this->artisan('marketing:warm')
            ->expectsOutputToContain('Google Analytics: 4 rapporten klaargezet')
            ->assertSuccessful();
    }

    public function test_fout_bij_google_analytics_laat_het_commando_niet_crashen(): void
    {
        Http::fake();

        $this->mock(GoogleAnalyticsService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            // Eén keer geprobeerd, daarna gestopt: de andere periodes zouden ook mislukken
            $mock->shouldReceive('refreshDashboard')->once()->andThrow(new RuntimeException('Google is onbereikbaar'));
        });

        $this->artisan('marketing:warm')->assertSuccessful();
    }

    public function test_google_analytics_wordt_overgeslagen_als_het_niet_is_ingesteld(): void
    {
        Http::fake();

        $this->mock(GoogleAnalyticsService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(false);
            $mock->shouldNotReceive('refreshDashboard');
        });

        $this->artisan('marketing:warm')->assertSuccessful();
    }
}