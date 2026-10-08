<?php

namespace Tests\Feature;

use App\Models\AdAccount;
use App\Services\MetaAdsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Test de nepdata van Meta (META_ADS_FAKE=true), vooral de campagnes.
 * De echte koppeling wordt getest in MetaAdsServiceLiveTest.
 */
class MetaAdsServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Altijd nepdata in deze tests, wat er ook in .env staat
        config(['services.meta_ads.fake' => true]);
        Cache::flush();
    }

    private function report(int $days = 28, string $accountId = '123456789'): array
    {
        return app(MetaAdsService::class)->getInsights(
            new AdAccount(['platform' => AdAccount::PLATFORM_META, 'account_id' => $accountId]),
            $days
        );
    }

    public function test_nepcampagnes_tellen_op_tot_het_totaal(): void
    {
        foreach (MetaAdsService::PERIODS as $days) {
            $report = $this->report($days);
            $campaigns = $report['campaigns'];

            $this->assertNotEmpty($campaigns);
            $this->assertSame($report['totals']['impressions'], array_sum(array_column($campaigns, 'impressions')));
            $this->assertSame($report['totals']['clicks'], array_sum(array_column($campaigns, 'clicks')));
            $this->assertSame($report['totals']['conversions'], array_sum(array_column($campaigns, 'conversions')));
            // Bij bedragen kan afronding een cent verschil geven
            $this->assertEqualsWithDelta($report['totals']['spend'], array_sum(array_column($campaigns, 'spend')), 0.01);
        }
    }

    public function test_elk_account_heeft_twee_tot_vier_campagnes_met_de_hoogste_besteding_bovenaan(): void
    {
        foreach (['111111111', '222222222', '333333333'] as $accountId) {
            $campaigns = $this->report(28, $accountId)['campaigns'];

            $this->assertGreaterThanOrEqual(2, count($campaigns));
            $this->assertLessThanOrEqual(4, count($campaigns));

            $spend = array_column($campaigns, 'spend');
            $sorted = $spend;
            rsort($sorted);

            $this->assertSame($sorted, $spend);
        }
    }

    public function test_nepcampagnes_zijn_elke_keer_hetzelfde(): void
    {
        $first = $this->report();
        Cache::flush(); // zonder cache opnieuw berekenen
        $second = $this->report();

        $this->assertSame($first['campaigns'], $second['campaigns']);
        $this->assertTrue($first['is_fake']);
    }

    public function test_met_nepdata_gaat_er_niets_naar_meta(): void
    {
        Http::fake();

        $this->report();

        Http::assertNothingSent();
    }
}