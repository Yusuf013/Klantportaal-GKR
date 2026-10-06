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

    public function test_haalt_echte_cijfers_op_en_telt_ze_goed_op(): void
    {
        $yesterday = CarbonImmutable::yesterday()->toDateString();
        $dayBefore = CarbonImmutable::yesterday()->subDay()->toDateString();

        Http::fake([
            'graph.facebook.com/*' => Http::response(['data' => [
                $this->row($dayBefore, 100, 4, '10.50', [['action_type' => 'lead', 'value' => '2']]),
                $this->row($yesterday, 200, 6, '5.25', [
                    ['action_type' => 'lead', 'value' => '1'],
                    ['action_type' => 'link_click', 'value' => '5'], // telt NIET als conversie
                ]),
            ]]),
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
        Http::fake(['graph.facebook.com/*' => Http::response(['data' => []])]);

        $report = app(MetaAdsService::class)->getInsights($this->account(), 7);

        $this->assertCount(7, $report['series']);
        $this->assertSame(0, $report['totals']['impressions']);
        $this->assertNull($report['trends']['impressions']);
    }

    public function test_token_gaat_alleen_mee_in_de_header_naar_meta(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['data' => []])]);

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
}