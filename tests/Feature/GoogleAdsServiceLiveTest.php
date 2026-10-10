<?php

namespace Tests\Feature;

use App\Models\AdAccount;
use App\Services\GoogleAccessToken;
use App\Services\GoogleAdsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

/**
 * Test de echte koppeling met Google Ads (GOOGLE_ADS_FAKE=false).
 *
 * Er gaat nooit een echt verzoek naar Google:
 * - Http::fake() doet alsof Google Ads antwoordt.
 * - Het inloggen van het robotaccount (GoogleAccessToken) is vervangen door een
 *   nepversie die altijd de code "test-token" teruggeeft.
 *
 * Vaste datum: het is 10 oktober 2026. "7 dagen" loopt dan van 3 t/m 9 oktober,
 * de periode ervoor van 26 september t/m 2 oktober.
 */
class GoogleAdsServiceLiveTest extends TestCase
{
    private const URL = 'https://googleads.googleapis.com/v25/customers/1234567890/googleAds:search';

    // ---------- Hulpfuncties ----------

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-10 12:00:00'));

        config([
            'services.google_ads.fake'              => false,
            'services.google_ads.api_version'       => 'v25',
            'services.google_ads.login_customer_id' => null,
        ]);
        Cache::flush();

        $this->mock(GoogleAccessToken::class, function ($mock) {
            $mock->shouldReceive('forScope')
                ->with('https://www.googleapis.com/auth/adwords')
                ->andReturn('test-token');
        });
    }

    private function account(string $customerId = '1234567890'): AdAccount
    {
        // Niet opgeslagen: de service heeft alleen platform en nummer nodig
        return new AdAccount(['platform' => AdAccount::PLATFORM_GOOGLE_ADS, 'account_id' => $customerId]);
    }

    private function report(int $days = 7, string $customerId = '1234567890'): array
    {
        return app(GoogleAdsService::class)->getInsights($this->account($customerId), $days);
    }

    /**
     * Eén rij zoals Google Ads die teruggeeft. Let op: gehele getallen komen als tekst,
     * en kosten in micros (1.000.000 = € 1).
     */
    private function row(string $date, string $campaignId, string $name, int $impressions, int $clicks, float $conversions, int $costMicros): array
    {
        return [
            'campaign' => ['resourceName' => "customers/1234567890/campaigns/{$campaignId}", 'id' => $campaignId, 'name' => $name],
            'metrics'  => [
                'impressions' => (string) $impressions,
                'clicks'      => (string) $clicks,
                'conversions' => $conversions,
                'costMicros'  => (string) $costMicros,
            ],
            'segments' => ['date' => $date],
        ];
    }

    /**
     * Google Ads antwoordt eerst met de rijen van deze periode en daarna met die van de vorige.
     * (De service vraagt ze altijd in die volgorde op.)
     */
    private function fakeGoogleAds(array $current, array $previous = []): void
    {
        Http::fake([
            'googleads.googleapis.com/*' => Http::sequence()
                ->push(['results' => $current, 'fieldMask' => 'campaign.id'])
                ->push(['results' => $previous, 'fieldMask' => 'campaign.id']),
        ]);
    }

    /**
     * Google Ads antwoordt op elk verzoek met dezelfde fout.
     */
    private function fakeGoogleAdsError(int $status, string $googleStatus, ?array $errorCode = null): void
    {
        $error = ['code' => $status, 'message' => 'Fout van Google', 'status' => $googleStatus];

        if ($errorCode) {
            $error['details'] = [['errors' => [['errorCode' => $errorCode, 'message' => 'Details van Google']]]];
        }

        Http::fake(['googleads.googleapis.com/*' => Http::response(['error' => $error], $status)]);
    }

    private function standardRows(): array
    {
        return [
            $this->row('2026-10-08', '111', 'Zoeken - Merknaam', 1000, 50, 2.0, 12_500_000),
            $this->row('2026-10-09', '111', 'Zoeken - Merknaam', 500, 25, 1.5, 7_250_000),
            $this->row('2026-10-09', '222', 'Performance Max', 2000, 40, 0.5, 30_000_000),
        ];
    }

    // ---------- 1. Cijfers ophalen en omrekenen ----------

    public function test_totalen_kloppen_en_kosten_zijn_omgerekend_naar_euro(): void
    {
        $this->fakeGoogleAds($this->standardRows());

        $report = $this->report();

        $this->assertFalse($report['is_fake']);
        $this->assertSame('2026-10-03', $report['start']);
        $this->assertSame('2026-10-09', $report['end']);
        $this->assertSame([
            'impressions' => 3500,
            'clicks'      => 115,
            'ctr'         => 3.29,
            'conversions' => 4.0,
            'spend'       => 49.75,   // 12.500.000 + 7.250.000 + 30.000.000 micros
        ], $report['totals']);
    }

    public function test_grafiek_heeft_elke_dag_ook_dagen_zonder_cijfers(): void
    {
        $this->fakeGoogleAds($this->standardRows());

        $series = $this->report()['series'];

        $this->assertCount(7, $series);
        $this->assertSame(['date' => '2026-10-03', 'impressions' => 0, 'clicks' => 0, 'conversions' => 0.0, 'spend' => 0.0], $series[0]);
        // 9 oktober: twee campagnes bij elkaar opgeteld
        $this->assertSame(['date' => '2026-10-09', 'impressions' => 2500, 'clicks' => 65, 'conversions' => 2.0, 'spend' => 37.25], $series[6]);
    }

    public function test_campagnes_zijn_opgeteld_met_de_hoogste_kosten_bovenaan(): void
    {
        $this->fakeGoogleAds($this->standardRows());

        $this->assertSame([
            ['name' => 'Performance Max', 'impressions' => 2000, 'clicks' => 40, 'ctr' => 2.0, 'conversions' => 0.5, 'spend' => 30.0],
            ['name' => 'Zoeken - Merknaam', 'impressions' => 1500, 'clicks' => 75, 'ctr' => 5.0, 'conversions' => 3.5, 'spend' => 19.75],
        ], $this->report()['campaigns']);
    }

    public function test_trend_vergelijkt_met_de_periode_ervoor(): void
    {
        $this->fakeGoogleAds(
            [$this->row('2026-10-09', '111', 'Zoeken', 1500, 60, 3.0, 20_000_000)],
            [$this->row('2026-10-01', '111', 'Zoeken', 1000, 50, 2.0, 10_000_000)]
        );

        $report = $this->report();

        $this->assertSame(1000, $report['previous']['impressions']);
        $this->assertSame(50.0, $report['trends']['impressions']);
        $this->assertSame(20.0, $report['trends']['clicks']);
        $this->assertSame(100.0, $report['trends']['spend']);
    }

    public function test_totaal_conversies_wordt_niet_per_dag_afgerond(): void
    {
        // Google verdeelt een conversie soms over meerdere klikken: 3 x 0,333333 = 1 conversie.
        // Eerst per dag afronden (0,33) en dan optellen zou 0,99 geven.
        $this->fakeGoogleAds([
            $this->row('2026-10-07', '111', 'Zoeken', 100, 10, 0.333333, 1_000_000),
            $this->row('2026-10-08', '111', 'Zoeken', 100, 10, 0.333333, 1_000_000),
            $this->row('2026-10-09', '111', 'Zoeken', 100, 10, 0.333334, 1_000_000),
        ]);

        $this->assertSame(1.0, $this->report()['totals']['conversions']);
    }

    public function test_rij_buiten_de_gevraagde_periode_telt_niet_mee(): void
    {
        // Mocht Google ooit een dag teruggeven waar we niet om vroegen, dan negeren we die
        $this->fakeGoogleAds([
            $this->row('2026-10-09', '111', 'Zoeken', 1000, 50, 2.0, 10_000_000),
            $this->row('2026-10-10', '111', 'Zoeken', 9999, 99, 9.0, 99_000_000),
        ]);

        $report = $this->report();

        $this->assertSame(1000, $report['totals']['impressions']);
        $this->assertSame(10.0, $report['totals']['spend']);
        $this->assertSame(1000, $report['campaigns'][0]['impressions']);
    }

    public function test_account_zonder_cijfers_geeft_nullen_en_geen_fout(): void
    {
        // Zonder cijfers laat Google de sleutel "results" helemaal weg
        Http::fake(['googleads.googleapis.com/*' => Http::response(['fieldMask' => 'campaign.id'])]);

        $report = $this->report();

        $this->assertSame(0, $report['totals']['impressions']);
        $this->assertSame(0.0, $report['totals']['spend']);
        $this->assertSame([], $report['campaigns']);
        $this->assertNull($report['trends']['clicks']);
        $this->assertCount(7, $report['series']);
    }

    // ---------- 2. Het verzoek aan Google ----------

    public function test_verzoek_gaat_naar_het_juiste_account_met_de_toegangscode(): void
    {
        $this->fakeGoogleAds($this->standardRows());

        $this->report();

        Http::assertSentCount(2); // deze periode + de periode ervoor
        Http::assertSent(fn ($request) => $request->url() === self::URL
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer test-token')
            // Sinds september 2026 is er geen developer token meer
            && ! $request->hasHeader('developer-token')
            // Geen beheeraccount ingesteld, dus die kopregel sturen we niet mee
            && ! $request->hasHeader('login-customer-id'));
    }

    public function test_vraag_bevat_beide_periodes_en_de_juiste_velden(): void
    {
        $this->fakeGoogleAds($this->standardRows());

        $this->report();

        $queries = Http::recorded()->map(fn ($pair) => $pair[0]->data()['query'])->all();

        $this->assertStringContainsString("segments.date BETWEEN '2026-10-03' AND '2026-10-09'", $queries[0]);
        $this->assertStringContainsString("segments.date BETWEEN '2026-09-26' AND '2026-10-02'", $queries[1]);
        $this->assertStringContainsString('FROM campaign', $queries[0]);

        foreach (['campaign.id', 'campaign.name', 'metrics.impressions', 'metrics.clicks', 'metrics.conversions', 'metrics.cost_micros'] as $field) {
            $this->assertStringContainsString($field, $queries[0]);
        }
    }

    public function test_met_een_beheeraccount_gaat_het_nummer_daarvan_mee(): void
    {
        config(['services.google_ads.login_customer_id' => '987-654-3210']);
        $this->fakeGoogleAds($this->standardRows());

        $this->report();

        Http::assertSent(fn ($request) => $request->hasHeader('login-customer-id', '9876543210'));
    }

    public function test_meerdere_paginas_worden_allemaal_opgehaald(): void
    {
        Http::fake([
            'googleads.googleapis.com/*' => Http::sequence()
                ->push(['results' => [$this->row('2026-10-08', '111', 'Zoeken', 1000, 50, 2.0, 10_000_000)], 'nextPageToken' => 'pagina-2'])
                ->push(['results' => [$this->row('2026-10-09', '111', 'Zoeken', 500, 25, 1.0, 5_000_000)]])
                ->push(['results' => []]),
        ]);

        $report = $this->report();

        $this->assertSame(1500, $report['totals']['impressions']);
        $this->assertSame(15.0, $report['totals']['spend']);

        Http::assertSentCount(3);
        // Het tweede verzoek vraagt om de volgende pagina
        $this->assertSame('pagina-2', Http::recorded()[1][0]->data()['pageToken']);
    }

    public function test_te_veel_paginas_geeft_een_fout_in_plaats_van_halve_cijfers(): void
    {
        Http::fake(['googleads.googleapis.com/*' => Http::response(['results' => [], 'nextPageToken' => 'nog-een'])]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('meer gegevens terug dan verwacht');

        $this->report();
    }

    public function test_toegangscode_wordt_maar_een_keer_opgevraagd(): void
    {
        $this->mock(GoogleAccessToken::class, function ($mock) {
            $mock->shouldReceive('forScope')->once()->andReturn('test-token');
        });
        $this->fakeGoogleAds($this->standardRows());

        $this->report();

        Http::assertSentCount(2);
    }

    // ---------- 3. Cache ----------

    public function test_tweede_keer_komt_het_rapport_uit_de_cache(): void
    {
        $this->fakeGoogleAds($this->standardRows());

        $first = $this->report();
        $second = $this->report();

        $this->assertSame($first, $second);
        Http::assertSentCount(2); // geen nieuwe verzoeken bij de tweede keer
    }

    public function test_een_fout_wordt_niet_onthouden(): void
    {
        Http::fake([
            'googleads.googleapis.com/*' => Http::sequence()
                ->push(['error' => ['code' => 500, 'status' => 'INTERNAL']], 500)
                ->push(['results' => $this->standardRows()])
                ->push(['results' => []]),
        ]);

        try {
            $this->report();
            $this->fail('Er had een fout moeten komen.');
        } catch (RuntimeException) {
            // verwacht
        }

        // Daarna werkt Google weer: het portaal probeert het gewoon opnieuw
        $this->assertSame(3500, $this->report()['totals']['impressions']);
    }

    // ---------- 4. Fouten ----------

    public function test_geen_toegang_geeft_een_duidelijke_melding(): void
    {
        $this->fakeGoogleAdsError(403, 'PERMISSION_DENIED', ['authorizationError' => 'USER_PERMISSION_DENIED']);

        try {
            $this->report();
            $this->fail('Er had een fout moeten komen.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('robotaccount heeft geen toegang', $e->getMessage());
            $this->assertStringContainsString('(403 USER_PERMISSION_DENIED)', $e->getMessage());
            // De toegangscode mag nooit in een melding of log terechtkomen
            $this->assertStringNotContainsString('test-token', $e->getMessage());
        }
    }

    public function test_cloud_project_zonder_toegang_geeft_een_eigen_melding(): void
    {
        $this->fakeGoogleAdsError(403, 'PERMISSION_DENIED', ['authorizationError' => 'CLOUD_PROJECT_NOT_APPROVED_FOR_PRODUCTION']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Google Cloud-project heeft nog geen toegang');

        $this->report();
    }

    public function test_daglimiet_bereikt_geeft_een_duidelijke_melding(): void
    {
        $this->fakeGoogleAdsError(429, 'RESOURCE_EXHAUSTED', ['quotaError' => 'RESOURCE_EXHAUSTED']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('daglimiet van Google Ads is bereikt');

        $this->report();
    }

    public function test_onbekende_fout_zonder_details_geeft_de_status_van_google(): void
    {
        $this->fakeGoogleAdsError(500, 'INTERNAL');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Google Ads gaf een fout. (500 INTERNAL)');

        $this->report();
    }

    public function test_onbereikbaar_geeft_een_melding_zonder_technische_details(): void
    {
        Http::fake(['googleads.googleapis.com/*' => Http::failedConnection()]);

        try {
            $this->report();
            $this->fail('Er had een fout moeten komen.');
        } catch (RuntimeException $e) {
            $this->assertSame('Google Ads is op dit moment niet bereikbaar.', $e->getMessage());
        }
    }

    public function test_ongeldig_klantnummer_wordt_geweigerd_zonder_verzoek(): void
    {
        Http::fake();

        try {
            $this->report(7, '12345/../6789');
            $this->fail('Er had een fout moeten komen.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('10 cijfers', $e->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_ongeldig_nummer_van_het_beheeraccount_wordt_geweigerd(): void
    {
        config(['services.google_ads.login_customer_id' => '12345']);
        Http::fake();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('GOOGLE_ADS_LOGIN_CUSTOMER_ID is ongeldig');

        $this->report();
    }
}