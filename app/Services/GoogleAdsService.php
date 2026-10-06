<?php

namespace App\Services;

use App\Models\AdAccount;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Random\Engine\Mt19937;
use Random\Randomizer;
use RuntimeException;

/**
 * Haalt advertentiecijfers op voor één Google Ads-account.
 *
 * Voorlopig met nepdata (GOOGLE_ADS_FAKE=true). Later vervangen we alleen
 * fetchCampaignDailyRows() door een echte aanroep naar de Google Ads API;
 * de rest (periodes, totalen, trends, campagnes, cache) blijft hetzelfde.
 *
 * Het rapport heeft bewust dezelfde opbouw als dat van MetaAdsService
 * (totals, previous, trends, series), zodat we later hetzelfde
 * weergaveblok kunnen gebruiken. Nieuw is 'campaigns': cijfers per campagne.
 */
class GoogleAdsService
{
    public const PERIODS = [7, 28, 90, 365];
    public const DEFAULT_PERIOD = 28;

    private const METRICS = ['impressions', 'clicks', 'conversions', 'spend'];

    // Namen voor de nepdata. Echte campagnenamen komen later uit Google Ads.
    private const FAKE_CAMPAIGNS = [
        'Zoeken - Merknaam',
        'Zoeken - Producten',
        'Zoeken - Diensten',
        'Performance Max',
        'Display - Remarketing',
    ];

    /**
     * Let op: deze methode vraagt bewust een AdAccount-model en geen los nummer.
     * Zo moet de aanroeper het account altijd uit de database halen
     * (bijv. via auth()->user()->googleAdsAccount) en nooit uit de URL.
     */
    public function getInsights(AdAccount $account, int $days): array
    {
        if ($account->platform !== AdAccount::PLATFORM_GOOGLE_ADS) {
            throw new InvalidArgumentException('Dit is geen Google Ads-account.');
        }

        // Onbekende periode? Dan standaard 28 dagen (zelfde als bij GA en Meta)
        $days = in_array($days, self::PERIODS, true) ? $days : self::DEFAULT_PERIOD;

        // Einddatum is gisteren: de cijfers van vandaag zijn nog niet compleet
        $end = CarbonImmutable::yesterday();

        // Nepdata en echte data krijgen een eigen cachesleutel, zodat je bij
        // het omschakelen nooit nog oude nepcijfers ziet
        $mode = $this->usesFakeData() ? 'fake' : 'live';
        $cacheKey = "google_ads:{$mode}:{$account->account_id}:{$days}:{$end->toDateString()}";

        return Cache::remember(
            $cacheKey,
            now()->addMinutes((int) config('services.google_ads.cache_minutes', 180)),
            fn () => $this->buildReport($account->account_id, $days, $end)
        );
    }

    public function usesFakeData(): bool
    {
        return (bool) config('services.google_ads.fake', true);
    }

    /**
     * Google toont klantnummers als 123-456-7890. Wij slaan alleen de cijfers op.
     */
    public static function normalizeCustomerId(string $value): string
    {
        return preg_replace('/[\s-]/', '', trim($value));
    }

    private function buildReport(string $accountId, int $days, CarbonImmutable $end): array
    {
        // Huidige periode en een even lange periode ervoor (voor de trend)
        $start = $end->subDays($days - 1);
        $previousEnd = $start->subDay();
        $previousStart = $previousEnd->subDays($days - 1);

        $currentRows = $this->fetchCampaignDailyRows($accountId, $start, $end);
        $previousRows = $this->fetchCampaignDailyRows($accountId, $previousStart, $previousEnd);

        // Per dag optellen over alle campagnes = de cijfers van het hele account
        $daily = $this->sumPerDay($currentRows, $start, $end);
        $previousDaily = $this->sumPerDay($previousRows, $previousStart, $previousEnd);

        $totals = $this->totals($daily);
        $previous = $this->totals($previousDaily);

        $trends = [];
        foreach ($totals as $metric => $value) {
            $trends[$metric] = $this->percentChange($value, $previous[$metric]);
        }

        return [
            'is_fake'   => $this->usesFakeData(),
            'days'      => $days,
            'start'     => $start->toDateString(),
            'end'       => $end->toDateString(),
            'totals'    => $totals,
            'previous'  => $previous,
            'trends'    => $trends,
            // Bij 12 maanden per maand, anders per dag (net als bij GA en Meta)
            'series'    => $days === 365 ? $this->groupByMonth($daily) : $daily,
            'campaigns' => $this->campaignTotals($currentRows),
        ];
    }

    /**
     * Cijfers per campagne per dag. Elke rij:
     * date, campaign_id, campaign_name, impressions, clicks, conversions, spend (in euro's).
     */
    private function fetchCampaignDailyRows(string $accountId, CarbonImmutable $start, CarbonImmutable $end): array
    {
        if ($this->usesFakeData()) {
            return $this->fakeCampaignDailyRows($accountId, $start, $end);
        }

        // Volgt in stap 4: echte aanroep naar de Google Ads API.
        // Let op voor dan: Google geeft kosten in micros (1.000.000 = € 1).
        throw new RuntimeException('De echte Google Ads-koppeling is nog niet gebouwd. Zet GOOGLE_ADS_FAKE=true.');
    }

    /**
     * Nepdata die er realistisch uitziet en altijd hetzelfde is voor
     * hetzelfde account op dezelfde dag (zelfde idee als bij Meta).
     * Elk account krijgt 2 tot 4 vaste campagnes.
     */
    private function fakeCampaignDailyRows(string $accountId, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $picker = new Randomizer(new Mt19937(crc32('campaigns|' . $accountId)));
        $campaigns = array_slice($picker->shuffleArray(self::FAKE_CAMPAIGNS), 0, $picker->getInt(2, 4));

        $rows = [];

        for ($date = $start; $date->lte($end); $date = $date->addDay()) {
            foreach ($campaigns as $index => $name) {
                $random = new Randomizer(new Mt19937(crc32($accountId . '|' . $index . '|' . $date->toDateString())));

                $impressions = $random->getInt(150, 1200);
                $clicks = intdiv($impressions * $random->getInt(15, 60), 1000);        // CTR tussen 1,5% en 6%
                // Google kan conversies verdelen over meerdere klikken, dus kommagetallen zijn normaal
                $conversions = round($clicks * $random->getInt(0, 800) / 10000, 2);   // 0% tot 8% van de klikken
                $spend = round($clicks * $random->getInt(40, 180) / 100, 2);          // € 0,40 tot € 1,80 per klik

                $rows[] = [
                    'date'          => $date->toDateString(),
                    'campaign_id'   => (string) ($index + 1),
                    'campaign_name' => $name,
                    'impressions'   => $impressions,
                    'clicks'        => $clicks,
                    'conversions'   => $conversions,
                    'spend'         => $spend,
                ];
            }
        }

        return $rows;
    }

    /**
     * Telt alle campagnes per dag op. Dagen zonder cijfers krijgen nullen,
     * zodat de grafiek geen gaten heeft.
     */
    private function sumPerDay(array $rows, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $days = [];

        for ($date = $start; $date->lte($end); $date = $date->addDay()) {
            $key = $date->toDateString();
            $days[$key] = ['date' => $key, 'impressions' => 0, 'clicks' => 0, 'conversions' => 0.0, 'spend' => 0.0];
        }

        foreach ($rows as $row) {
            // Veiligheid: een rij buiten de periode tellen we niet mee
            if (! isset($days[$row['date']])) {
                continue;
            }

            foreach (self::METRICS as $metric) {
                $days[$row['date']][$metric] += $row[$metric];
            }
        }

        return array_values(array_map(
            fn ($day) => [...$day, 'conversions' => round($day['conversions'], 2), 'spend' => round($day['spend'], 2)],
            $days
        ));
    }

    /**
     * Totalen per campagne over de hele periode, hoogste kosten bovenaan.
     */
    private function campaignTotals(array $rows): array
    {
        $campaigns = [];

        foreach ($rows as $row) {
            $id = $row['campaign_id'];
            $campaigns[$id] ??= ['name' => $row['campaign_name'], 'impressions' => 0, 'clicks' => 0, 'conversions' => 0.0, 'spend' => 0.0];

            foreach (self::METRICS as $metric) {
                $campaigns[$id][$metric] += $row[$metric];
            }
        }

        $result = [];
        foreach ($campaigns as $campaign) {
            // Campagnes zonder vertoningen in deze periode laten we weg
            if ($campaign['impressions'] === 0) {
                continue;
            }

            $result[] = [
                'name'        => $campaign['name'],
                'impressions' => $campaign['impressions'],
                'clicks'      => $campaign['clicks'],
                'ctr'         => round($campaign['clicks'] / $campaign['impressions'] * 100, 2),
                'conversions' => round($campaign['conversions'], 2),
                'spend'       => round($campaign['spend'], 2),
            ];
        }

        usort($result, fn ($a, $b) => $b['spend'] <=> $a['spend']);

        return $result;
    }

    private function totals(array $rows): array
    {
        $impressions = array_sum(array_column($rows, 'impressions'));
        $clicks = array_sum(array_column($rows, 'clicks'));

        return [
            'impressions' => $impressions,
            'clicks'      => $clicks,
            // CTR rekenen we zelf uit, zodat het altijd klopt met de getoonde klikken en vertoningen
            'ctr'         => $impressions > 0 ? round($clicks / $impressions * 100, 2) : 0.0,
            'conversions' => round(array_sum(array_column($rows, 'conversions')), 2),
            'spend'       => round(array_sum(array_column($rows, 'spend')), 2),
        ];
    }

    private function percentChange(float $current, float $previous): ?float
    {
        // Geen vorige waarde? Dan kun je geen percentage berekenen
        if ($previous == 0) {
            return null;
        }

        return round(($current - $previous) / $previous * 100, 1);
    }

    private function groupByMonth(array $rows): array
    {
        $months = [];

        foreach ($rows as $row) {
            $month = substr($row['date'], 0, 7); // bijv. "2026-09"

            $months[$month] ??= ['date' => $month, 'impressions' => 0, 'clicks' => 0, 'conversions' => 0.0, 'spend' => 0.0];

            foreach (self::METRICS as $metric) {
                $months[$month][$metric] += $row[$metric];
            }
        }

        return array_values(array_map(
            fn ($month) => [...$month, 'conversions' => round($month['conversions'], 2), 'spend' => round($month['spend'], 2)],
            $months
        ));
    }
}