<?php

namespace Tests\Feature;

use App\Models\AdAccount;
use App\Services\GoogleAdsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class GoogleAdsServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Altijd nepdata in deze tests, wat er ook in .env staat
        config(['services.google_ads.fake' => true]);
        Cache::flush();
    }

    private function account(string $customerId = '1234567890'): AdAccount
    {
        // Niet opgeslagen: de service heeft alleen platform en nummer nodig
        return new AdAccount(['platform' => AdAccount::PLATFORM_GOOGLE_ADS, 'account_id' => $customerId]);
    }

    private function report(int $days = 28, string $customerId = '1234567890'): array
    {
        return app(GoogleAdsService::class)->getInsights($this->account($customerId), $days);
    }

    public function test_nepdata_is_elke_keer_hetzelfde(): void
    {
        $first = $this->report();
        Cache::flush(); // zonder cache opnieuw berekenen
        $second = $this->report();

        $this->assertSame($first['totals'], $second['totals']);
        $this->assertSame($first['campaigns'], $second['campaigns']);
        $this->assertTrue($first['is_fake']);
    }

    public function test_ander_account_geeft_andere_cijfers(): void
    {
        $this->assertNotSame(
            $this->report(28, '1111111111')['totals'],
            $this->report(28, '2222222222')['totals']
        );
    }

    public function test_weigert_een_meta_account(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(GoogleAdsService::class)->getInsights(
            new AdAccount(['platform' => AdAccount::PLATFORM_META, 'account_id' => '1234567890']),
            28
        );
    }

    public function test_onbekende_periode_wordt_28_dagen(): void
    {
        $report = $this->report(50);

        $this->assertSame(28, $report['days']);
        $this->assertCount(28, $report['series']);
    }

    public function test_twaalf_maanden_worden_per_maand_getoond(): void
    {
        $count = count($this->report(365)['series']);

        $this->assertGreaterThanOrEqual(12, $count);
        $this->assertLessThanOrEqual(13, $count);
    }

    public function test_campagnes_tellen_op_tot_het_totaal(): void
    {
        $report = $this->report();

        $this->assertNotEmpty($report['campaigns']);
        $this->assertSame($report['totals']['impressions'], array_sum(array_column($report['campaigns'], 'impressions')));
        $this->assertSame($report['totals']['clicks'], array_sum(array_column($report['campaigns'], 'clicks')));
        // Bij bedragen kan afronding een cent verschil geven
        $this->assertEqualsWithDelta($report['totals']['spend'], array_sum(array_column($report['campaigns'], 'spend')), 0.05);
    }

    public function test_ctr_klopt_met_klikken_en_vertoningen(): void
    {
        $totals = $this->report()['totals'];

        $this->assertSame(round($totals['clicks'] / $totals['impressions'] * 100, 2), $totals['ctr']);
    }

    public function test_echte_koppeling_zonder_sleutel_geeft_een_duidelijke_fout(): void
    {
        // Echte cijfers gevraagd, maar de sleutel van het robotaccount ontbreekt.
        // Er gaat dan geen enkel verzoek naar Google.
        config(['services.google_ads.fake' => false, 'services.google_analytics.credentials' => null]);
        Http::fake();

        try {
            $this->report();
            $this->fail('Er had een fout moeten komen.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('sleutel van het robotaccount is niet ingesteld', $e->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_klantnummer_met_streepjes_wordt_alleen_cijfers(): void
    {
        $this->assertSame('1234567890', GoogleAdsService::normalizeCustomerId(' 123-456-7890 '));
    }
}