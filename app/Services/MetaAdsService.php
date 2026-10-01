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
 * Haalt advertentiecijfers op voor één Meta-advertentieaccount.
 *
 * Voorlopig met nepdata (META_ADS_FAKE=true). Later vervangen we alleen
 * fetchDailyRows() door een echte aanroep naar de Meta Marketing API;
 * de rest (periodes, totalen, trends, cache) blijft hetzelfde.
 */
class MetaAdsService
{
    public const PERIODS = [7, 28, 90, 365];
    public const DEFAULT_PERIOD = 28;

    private const METRICS = ['impressions', 'clicks', 'conversions', 'spend'];

    /**
     * Let op: deze methode vraagt bewust een AdAccount-model en geen los nummer.
     * Zo moet de aanroeper het account altijd uit de database halen
     * (bijv. via auth()->user()->metaAdAccount) en nooit uit de URL.
     */
    public function getInsights(AdAccount $account, int $days): array
    {
        if ($account->platform !== AdAccount::PLATFORM_META) {
            throw new InvalidArgumentException('Dit is geen Meta-advertentieaccount.');
        }

        // Onbekende periode? Dan standaard 28 dagen (zelfde als bij Google Analytics)
        $days = in_array($days, self::PERIODS, true) ? $days : self::DEFAULT_PERIOD;

        // Einddatum is gisteren: de cijfers van vandaag zijn nog niet compleet
        $end = CarbonImmutable::yesterday();

        // Nepdata en echte data krijgen een eigen cachesleutel, zodat je bij
        // het omschakelen nooit nog oude nepcijfers ziet
        $mode = $this->usesFakeData() ? 'fake' : 'live';
        $cacheKey = "meta_ads:{$mode}:{$account->account_id}:{$days}:{$end->toDateString()}";

        return Cache::remember(
            $cacheKey,
            now()->addMinutes((int) config('services.meta_ads.cache_minutes', 180)),
            fn () => $this->buildReport($account->account_id, $days, $end)
        );
    }

    public function usesFakeData(): bool
    {
        return (bool) config('services.meta_ads.fake', true);
    }

    private function buildReport(string $accountId, int $days, CarbonImmutable $end): array
    {
        // Huidige periode en een even lange periode ervoor (voor de trend)
        $start = $end->subDays($days - 1);
        $previousEnd = $start->subDay();
        $previousStart = $previousEnd->subDays($days - 1);

        $currentRows = $this->fetchDailyRows($accountId, $start, $end);
        $previousRows = $this->fetchDailyRows($accountId, $previousStart, $previousEnd);

        $totals = $this->totals($currentRows);
        $previous = $this->totals($previousRows);

        $trends = [];
        foreach ($totals as $metric => $value) {
            $trends[$metric] = $this->percentChange($value, $previous[$metric]);
        }

        return [
            'is_fake'  => $this->usesFakeData(),
            'days'     => $days,
            'start'    => $start->toDateString(),
            'end'      => $end->toDateString(),
            'totals'   => $totals,
            'previous' => $previous,
            'trends'   => $trends,
            // Bij 12 maanden per maand, anders per dag (net als bij GA)
            'series'   => $days === 365 ? $this->groupByMonth($currentRows) : $currentRows,
        ];
    }

    /**
     * Cijfers per dag: impressions, clicks, conversions, spend.
     */
    private function fetchDailyRows(string $accountId, CarbonImmutable $start, CarbonImmutable $end): array
    {
        if ($this->usesFakeData()) {
            return $this->fakeDailyRows($accountId, $start, $end);
        }

        // Volgt later: echte aanroep naar de Meta Marketing API
        throw new RuntimeException('De echte Meta-koppeling is nog niet gebouwd. Zet META_ADS_FAKE=true.');
    }

    /**
     * Nepdata die er realistisch uitziet en altijd hetzelfde is voor
     * hetzelfde account op dezelfde dag. Daardoor:
     * - veranderen de cijfers niet bij elke refresh;
     * - heeft elke klant andere cijfers;
     * - zijn 7 dagen precies een deel van 28 dagen (net als echte data).
     */
    private function fakeDailyRows(string $accountId, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $rows = [];

        for ($date = $start; $date->lte($end); $date = $date->addDay()) {
            $random = new Randomizer(new Mt19937(crc32($accountId . '|' . $date->toDateString())));

            $impressions = $random->getInt(800, 2500);
            $clicks = intdiv($impressions * $random->getInt(8, 30), 1000);   // CTR tussen 0,8% en 3%
            $conversions = intdiv($clicks * $random->getInt(2, 8), 100);     // 2% tot 8% van de klikken
            $spend = round($impressions / 1000 * $random->getInt(400, 900) / 100, 2); // € 4 tot € 9 per 1000 vertoningen

            $rows[] = [
                'date'        => $date->toDateString(),
                'impressions' => $impressions,
                'clicks'      => $clicks,
                'conversions' => $conversions,
                'spend'       => $spend,
            ];
        }

        return $rows;
    }

    private function totals(array $rows): array
    {
        $impressions = array_sum(array_column($rows, 'impressions'));
        $clicks = array_sum(array_column($rows, 'clicks'));

        return [
            'impressions' => $impressions,
            'clicks'      => $clicks,
            // CTR rekenen we zelf uit, zodat het altijd klopt met de getoonde klikken en impressies
            'ctr'         => $impressions > 0 ? round($clicks / $impressions * 100, 2) : 0.0,
            'conversions' => array_sum(array_column($rows, 'conversions')),
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

            $months[$month] ??= ['date' => $month, 'impressions' => 0, 'clicks' => 0, 'conversions' => 0, 'spend' => 0.0];

            foreach (self::METRICS as $metric) {
                $months[$month][$metric] += $row[$metric];
            }
        }

        return array_values(array_map(
            fn ($month) => [...$month, 'spend' => round($month['spend'], 2)],
            $months
        ));
    }
}