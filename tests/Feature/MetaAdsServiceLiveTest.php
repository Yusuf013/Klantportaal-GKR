<?php

namespace Tests\Feature;

use App\Models\AdAccount;
use App\Services\MetaAdsService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * Test de echte Meta-koppeling ZONDER echt naar Meta te gaan:
 * Http::fake() doet alsof Meta antwoordt.
 *
 * De service doet per rapport drie aanvragen: cijfers per dag (deze periode),
 * cijfers per dag (vorige periode) en cijfers per campagne.
 */
class MetaAdsServiceLiveTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.meta_ads.fake' => false,
            'services.meta_ads.access_token' => 'test-token',
            'services.meta_ads.api_version' => 'v26.0',
            'services.meta_ads.conversion_action_types' => ['lead'],
        ]);
    }

    private function account(): AdAccount
    {
        return new AdAccount(['platform' => AdAccount::PLATFORM_META, 'account_id' => '123456789']);
    }

    private function row(string $date, int $impressions, int $clicks, string $spend, array $actions = []): array
    {
        return [
            'date_start'  => $date,
            'date_stop'   => $date,
            'impressions' => (string) $impressions,
            'clicks'      => (string) $clicks,
            'spend'       => $spend,
            'actions'     => $actions,
        ];
    }

    private function campaign(string $name, int $impressions, int $clicks, string $spend, array $actions = []): array
    {
        return [
            'campaign_id'   => (string) crc32($name),
            'campaign_name' => $name,
            'impressions'   => (string) $impressions,
            'clicks'        => (string) $clicks,
            'spend'         => $spend,
            'actions'       => $actions,
        ];
    }

    /**
     * Meta antwoordt met $daily op de vraag om cijfers per dag,
     * en met $campaigns op de vraag om cijfers per campagne.
     */
    private function fakeMeta(array $daily = [], array $campaigns = []): void
    {
        Http::fake(function ($request) use ($daily, $campaigns) {
            $isCampaignRequest = str_contains($request->url(), 'level=campaign');

            return Http::response(['data' => $isCampaignRequest ? $campaigns : $daily]);
        });
    }

    // ---------- Totalen per dag (ongewijzigd gedrag) ----------

    public function test_haalt_echte_cijfers_op_en_telt_ze_goed_op(): void
    {
        $yesterday = CarbonImmutable::yesterday()->toDateString();
        $dayBefore = CarbonImmutable::yesterday()->subDay()->toDateString();

        $this->fakeMeta([
            $this->row($dayBefore, 100, 4, '10.50', [['action_type' => 'lead', 'value' => '2']]),
            $this->row($yesterday, 200, 6, '5.25', [
                ['action_type' => 'lead', 'value' => '1'],
                ['action_type' => 'link_click', 'value' => '5'], // telt NIET als conversie
            ]),
        ]);

        $report = app(MetaAdsService::class)->getInsights($this->account(), 7);

        $this->assertFalse($report['is_fake']);
        $this->assertSame(300, $report['totals']['impressions']);
        $this->assertSame(10, $report['totals']['clicks']);
        $this->assertSame(3, $report['totals']['conversions']);
        $this->assertEquals(15.75, $report['totals']['spend']);
        $this->assertEquals(3.33, $report['totals']['ctr']);
    }

    public function test_dagen_zonder_advertenties_worden_aangevuld_met_nullen(): void
    {
        $this->fakeMeta();

        $report = app(MetaAdsService::class)->getInsights($this->account(), 7);

        $this->assertCount(7, $report['series']);
        $this->assertSame(0, $report['totals']['impressions']);
        $this->assertNull($report['trends']['impressions']);
        $this->assertSame([], $report['campaigns']);
    }

    public function test_token_gaat_alleen_mee_in_de_header_naar_meta(): void
    {
        $this->fakeMeta();

        app(MetaAdsService::class)->getInsights($this->account(), 7);

        Http::assertSent(fn ($request) =>
            str_starts_with($request->url(), 'https://graph.facebook.com/v26.0/act_123456789/insights')
            && $request->hasHeader('Authorization', 'Bearer test-token')
            && ! str_contains($request->url(), 'test-token')
        );
    }

    public function test_volgt_volgende_pagina_van_meta(): void
    {
        $yesterday = CarbonImmutable::yesterday()->toDateString();
        $dayBefore = CarbonImmutable::yesterday()->subDay()->toDateString();

        Http::fake([
            'graph.facebook.com/*' => Http::sequence()
                ->push(['data' => [$this->row($dayBefore, 100, 1, '1.00')], 'paging' => ['next' => 'https://graph.facebook.com/v26.0/volgende-pagina']])
                ->push(['data' => [$this->row($yesterday, 50, 1, '1.00')]])
                ->whenEmpty(Http::response(['data' => []])),
        ]);

        $report = app(MetaAdsService::class)->getInsights($this->account(), 7);

        $this->assertSame(150, $report['totals']['impressions']);
    }

    public function test_token_wordt_nooit_naar_een_ander_adres_gestuurd(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'data'   => [],
                'paging' => ['next' => 'https://evil.example.com/steel-token'],
            ]),
            '*' => Http::response(['data' => []]),
        ]);

        app(MetaAdsService::class)->getInsights($this->account(), 7);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'evil.example.com'));
    }

    public function test_fout_van_meta_geeft_een_exception(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid OAuth access token']], 400),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid OAuth access token');

        app(MetaAdsService::class)->getInsights($this->account(), 7);
    }

    public function test_zonder_token_geeft_een_duidelijke_fout(): void
    {
        config(['services.meta_ads.access_token' => null]);
        Http::fake();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('token of API-versie ontbreekt');

        app(MetaAdsService::class)->getInsights($this->account(), 7);
    }

    public function test_te_veel_paginas_geeft_een_fout_in_plaats_van_onvolledige_cijfers(): void
    {
        // Meta blijft zeggen dat er nog een pagina is
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'data'   => [],
                'paging' => ['next' => 'https://graph.facebook.com/v26.0/volgende-pagina'],
            ]),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('onvolledig');

        app(MetaAdsService::class)->getInsights($this->account(), 7);
    }

    // ---------- Campagnes (nieuw) ----------

    public function test_haalt_campagnes_op_met_de_hoogste_besteding_bovenaan(): void
    {
        $this->fakeMeta(campaigns: [
            $this->campaign('Klein', 100, 2, '3.10', [
                ['action_type' => 'lead', 'value' => '1'],
                ['action_type' => 'link_click', 'value' => '9'], // telt NIET als conversie
            ]),
            $this->campaign('Groot', 900, 30, '45.50', [['action_type' => 'lead', 'value' => '4']]),
        ]);

        $campaigns = app(MetaAdsService::class)->getInsights($this->account(), 28)['campaigns'];

        $this->assertSame(['Groot', 'Klein'], array_column($campaigns, 'name'));

        $this->assertSame(900, $campaigns[0]['impressions']);
        $this->assertSame(30, $campaigns[0]['clicks']);
        $this->assertEquals(3.33, $campaigns[0]['ctr']);
        $this->assertSame(4, $campaigns[0]['conversions']);
        $this->assertEquals(45.50, $campaigns[0]['spend']);

        $this->assertSame(1, $campaigns[1]['conversions']);
    }

    public function test_campagnes_komen_uit_een_aparte_aanvraag_met_een_rij_per_campagne(): void
    {
        $this->fakeMeta();

        app(MetaAdsService::class)->getInsights($this->account(), 365);

        // Deze periode per dag, vorige periode per dag, en één keer de campagnes
        Http::assertSentCount(3);

        // De campagne-aanvraag vraagt GEEN cijfers per dag: dan blijft hij klein, ook bij 12 maanden
        Http::assertSent(fn ($request) =>
            str_contains($request->url(), 'level=campaign')
            && str_contains($request->url(), 'campaign_name')
            && ! str_contains($request->url(), 'time_increment')
        );
    }

    public function test_campagne_die_niets_heeft_gedaan_wordt_weggelaten(): void
    {
        $this->fakeMeta(campaigns: [
            $this->campaign('Actief', 500, 10, '12.00'),
            $this->campaign('Stil', 0, 0, '0'),
        ]);

        $campaigns = app(MetaAdsService::class)->getInsights($this->account(), 28)['campaigns'];

        $this->assertSame(['Actief'], array_column($campaigns, 'name'));
    }

    public function test_totalen_komen_van_het_account_en_niet_van_de_campagnes(): void
    {
        // Meta kan campagnes weglaten (bijv. verwijderde). De totalen bovenaan mogen daardoor nooit veranderen.
        $yesterday = CarbonImmutable::yesterday()->toDateString();

        $this->fakeMeta(
            daily: [$this->row($yesterday, 300, 9, '20.00')],
            campaigns: [$this->campaign('Enige zichtbare campagne', 250, 7, '15.00')],
        );

        $report = app(MetaAdsService::class)->getInsights($this->account(), 7);

        $this->assertSame(300, $report['totals']['impressions']);
        $this->assertEquals(20.00, $report['totals']['spend']);
        $this->assertSame(250, $report['campaigns'][0]['impressions']);
    }
}