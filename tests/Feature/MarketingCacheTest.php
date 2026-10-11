<?php

namespace Tests\Feature;

use App\Models\AdAccount;
use App\Services\GoogleAnalyticsService;
use App\Services\MetaAdsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * Test hoe Meta en Google Analytics hun rapporten bewaren:
 * - ligt er een rapport klaar, dan wordt er niets opgehaald;
 * - een mislukte poging wordt een minuut onthouden, zodat een bezoeker niet bij elke
 *   paginaweergave opnieuw op Meta of Google wacht;
 * - verversen (wat de server op de achtergrond doet) haalt altijd opnieuw op.
 *
 * (Dezelfde tests voor Google Ads staan in GoogleAdsServiceLiveTest.)
 * Er gaat nooit een echt verzoek naar buiten.
 */
class MarketingCacheTest extends TestCase
{
    // ---------- Hulpfuncties ----------

    protected function setUp(): void
    {
        parent::setUp();

        // Vaste datum: het is 10 oktober 2026, dus "gisteren" is 9 oktober
        $this->travelTo(Carbon::parse('2026-10-10 12:00:00'));

        config([
            'services.meta_ads.fake'                    => false,
            'services.meta_ads.access_token'            => 'test-token',
            'services.meta_ads.api_version'             => 'v26.0',
            'services.meta_ads.conversion_action_types' => ['lead'],
        ]);
        Cache::flush();
    }

    private function metaAccount(): AdAccount
    {
        // Niet opgeslagen: de service heeft alleen platform en nummer nodig
        return new AdAccount(['platform' => AdAccount::PLATFORM_META, 'account_id' => '123456789']);
    }

    /**
     * Eén dagrij zoals Meta die teruggeeft, op 9 oktober 2026 (gisteren).
     */
    private function metaRow(int $impressions): array
    {
        return [
            'date_start'  => '2026-10-09',
            'date_stop'   => '2026-10-09',
            'impressions' => (string) $impressions,
            'clicks'      => '5',
            'spend'       => '10.00',
            'actions'     => [],
        ];
    }

    /**
     * Een nagemaakte Analytics-service: het echte ophalen bij Google is vervangen.
     * $calls telt hoe vaak er "bij Google" is opgehaald.
     */
    private function analytics(): GoogleAnalyticsService
    {
        return new class extends GoogleAnalyticsService
        {
            public int $calls = 0;

            public bool $fail = false;

            public int $users = 100;

            protected function fetchDashboard(int $days): array
            {
                $this->calls++;

                if ($this->fail) {
                    throw new RuntimeException('Google Analytics is onbereikbaar');
                }

                return ['days' => $days, 'users' => $this->users];
            }
        };
    }

    // ---------- 1. Meta ----------

    public function test_meta_fout_wordt_een_minuut_onthouden_en_daarna_opnieuw_geprobeerd(): void
    {
        $fail = true;

        Http::fake(function () use (&$fail) {
            return $fail
                ? Http::response(['error' => ['message' => 'Tijdelijke storing']], 500)
                : Http::response(['data' => []]);
        });

        $service = app(MetaAdsService::class);

        // 1. Meta geeft een fout
        try {
            $service->getInsights($this->metaAccount(), 7);
            $this->fail('Er had een fout moeten komen.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Tijdelijke storing', $e->getMessage());
        }

        Http::assertSentCount(1);

        // 2. Meteen daarna: het portaal wacht niet opnieuw op Meta, maar meldt direct dat het misging
        try {
            $service->getInsights($this->metaAccount(), 7);
            $this->fail('Er had een fout moeten komen.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('zojuist mislukt', $e->getMessage());
        }

        Http::assertSentCount(1); // geen nieuw verzoek

        // 3. Een minuut later werkt Meta weer: het portaal probeert het opnieuw
        $fail = false;
        $this->travel(61)->seconds();

        $this->assertFalse($service->getInsights($this->metaAccount(), 7)['is_fake']);
        Http::assertSentCount(4); // 1 mislukt + 3 voor het rapport
    }

    public function test_meta_tweede_keer_komt_het_rapport_uit_de_cache(): void
    {
        Http::fake(fn () => Http::response(['data' => []]));

        $service = app(MetaAdsService::class);

        $first = $service->getInsights($this->metaAccount(), 7);
        $second = $service->getInsights($this->metaAccount(), 7);

        $this->assertSame($first, $second);
        Http::assertSentCount(3); // geen nieuwe verzoeken bij de tweede keer
    }

    public function test_meta_verversen_haalt_altijd_opnieuw_op_en_vervangt_het_oude_rapport(): void
    {
        // Meta geeft elke keer de cijfers die op dat moment in $impressions staan
        $impressions = 1000;

        Http::fake(function ($request) use (&$impressions) {
            // De aanvraag voor de campagnes laten we leeg
            return str_contains($request->url(), 'level=campaign')
                ? Http::response(['data' => []])
                : Http::response(['data' => [$this->metaRow($impressions)]]);
        });

        $service = app(MetaAdsService::class);

        $this->assertSame(1000, $service->getInsights($this->metaAccount(), 7)['totals']['impressions']);

        // Bij Meta zijn de cijfers bijgewerkt. De pagina merkt daar niets van: die leest het bewaarde rapport
        $impressions = 1500;
        $this->assertSame(1000, $service->getInsights($this->metaAccount(), 7)['totals']['impressions']);

        // Verversen (wat de server op de achtergrond doet) haalt wel opnieuw op...
        $this->assertSame(1500, $service->refresh($this->metaAccount(), 7)['totals']['impressions']);

        // ...en daarna krijgt de pagina het nieuwe rapport, zonder zelf iets op te halen
        $this->assertSame(1500, $service->getInsights($this->metaAccount(), 7)['totals']['impressions']);
        Http::assertSentCount(6); // twee keer opgehaald, elke keer 3 verzoeken
    }

    // ---------- 2. Google Analytics ----------

    public function test_analytics_tweede_keer_komt_het_rapport_uit_de_cache(): void
    {
        $analytics = $this->analytics();

        $first = $analytics->getDashboard(28);
        $second = $analytics->getDashboard(28);

        $this->assertSame($first, $second);
        $this->assertSame(1, $analytics->calls);
    }

    public function test_analytics_fout_wordt_een_minuut_onthouden_en_daarna_opnieuw_geprobeerd(): void
    {
        $analytics = $this->analytics();
        $analytics->fail = true;

        // 1. Google geeft een fout
        try {
            $analytics->getDashboard(28);
            $this->fail('Er had een fout moeten komen.');
        } catch (RuntimeException $e) {
            $this->assertSame('Google Analytics is onbereikbaar', $e->getMessage());
        }

        // 2. Meteen daarna: niet opnieuw wachten op Google
        try {
            $analytics->getDashboard(28);
            $this->fail('Er had een fout moeten komen.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('zojuist mislukt', $e->getMessage());
        }

        $this->assertSame(1, $analytics->calls);

        // 3. Een minuut later werkt Google weer
        $analytics->fail = false;
        $this->travel(61)->seconds();

        $this->assertSame(100, $analytics->getDashboard(28)['users']);
        $this->assertSame(2, $analytics->calls);
    }

    public function test_analytics_knop_vernieuwen_probeert_het_meteen_opnieuw(): void
    {
        $analytics = $this->analytics();
        $analytics->fail = true;

        try {
            $analytics->getDashboard(28);
        } catch (RuntimeException) {
            // verwacht
        }

        // De medewerker klikt op "Vernieuwen": dat leegt de cache, ook het "zojuist mislukt"
        $analytics->fail = false;
        $analytics->clearCache();

        $this->assertSame(100, $analytics->getDashboard(28)['users']);
    }

    public function test_analytics_verversen_haalt_altijd_opnieuw_op_en_vervangt_het_oude_rapport(): void
    {
        $analytics = $this->analytics();

        $this->assertSame(100, $analytics->getDashboard(28)['users']);

        // Bij Google zijn de cijfers bijgewerkt. De pagina leest nog het bewaarde rapport
        $analytics->users = 150;
        $this->assertSame(100, $analytics->getDashboard(28)['users']);

        // Verversen haalt wel opnieuw op, en daarna krijgt de pagina het nieuwe rapport
        $this->assertSame(150, $analytics->refreshDashboard(28)['users']);
        $this->assertSame(150, $analytics->getDashboard(28)['users']);
        $this->assertSame(2, $analytics->calls);
    }

    public function test_analytics_onbekende_periode_valt_terug_op_28_dagen(): void
    {
        $analytics = $this->analytics();

        $this->assertSame(28, $analytics->getDashboard(5)['days']);
        $this->assertSame(28, $analytics->refreshDashboard(5)['days']);
    }
}