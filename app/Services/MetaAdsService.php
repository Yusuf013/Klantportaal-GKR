<?php

namespace App\Services;

use App\Models\AdAccount;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Random\Engine\Mt19937;
use Random\Randomizer;
use RuntimeException;

/**
 * Haalt advertentiecijfers op voor één Meta-advertentieaccount.
 *
 * META_ADS_FAKE=true  -> nepdata (voor demo's en lokaal ontwikkelen)
 * META_ADS_FAKE=false -> echte cijfers via de Meta Marketing API
 *
 * De rest (periodes, totalen, trends, cache) is voor beide hetzelfde.
 */
class MetaAdsService
{
    public const PERIODS = [7, 28, 90, 365];
    public const DEFAULT_PERIOD = 28;

    private const METRICS = ['impressions', 'clicks', 'conversions', 'spend'];

    // De token wordt alleen naar dit adres gestuurd, nergens anders naartoe
    private const GRAPH_URL = 'https://graph.facebook.com/';

    // Veiligheidsgrens: nooit eindeloos doorbladeren
    private const MAX_PAGES = 10;

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
     * Cijfers per dag: date, impressions, clicks, conversions, spend.
     */
    private function fetchDailyRows(string $accountId, CarbonImmutable $start, CarbonImmutable $end): array
    {
        if ($this->usesFakeData()) {
            return $this->fakeDailyRows($accountId, $start, $end);
        }

        return $this->liveDailyRows($accountId, $start, $end);
    }

    /**
     * Echte cijfers per dag via de Meta Marketing API (Insights).
     */
    private function liveDailyRows(string $accountId, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $token = config('services.meta_ads.access_token');
        $version = config('services.meta_ads.api_version');

        if (blank($token) || blank($version)) {
            throw new RuntimeException('Meta-koppeling is niet ingesteld: token of API-versie ontbreekt.');
        }

        $url = self::GRAPH_URL . "{$version}/act_{$accountId}/insights";
        $query = [
            'level'          => 'account',
            'fields'         => 'impressions,clicks,spend,actions',
            'time_range'     => json_encode(['since' => $start->toDateString(), 'until' => $end->toDateString()]),
            'time_increment' => 1,   // één rij per dag
            'limit'          => 100,
        ];

        $byDate = [];
        $pages = 0;

        while ($url && $pages < self::MAX_PAGES) {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(15)
                // Alleen opnieuw proberen bij netwerkproblemen, niet bij bijv. een ongeldige token
                ->retry(2, 500, fn ($e) => $e instanceof ConnectionException, throw: false)
                ->get($url, $query);

            if ($response->failed()) {
                // Foutmelding van Meta (zonder token) doorgeven; de controller logt hem
                $message = $response->json('error.message') ?? 'onbekende fout';
                throw new RuntimeException("Meta API gaf een fout ({$response->status()}): {$message}");
            }

            foreach ($response->json('data', []) as $row) {
                $byDate[$row['date_start']] = [
                    'impressions' => (int) ($row['impressions'] ?? 0),
                    'clicks'      => (int) ($row['clicks'] ?? 0),
                    'conversions' => $this->countConversions($row['actions'] ?? []),
                    'spend'       => round((float) ($row['spend'] ?? 0), 2),
                ];
            }

            // Volgende pagina? Die link bevat alle parameters al.
            // Alleen volgen als hij echt naar Meta wijst, zodat de token nooit ergens anders heen gaat.
            $next = $response->json('paging.next');
            $url = (is_string($next) && str_starts_with($next, self::GRAPH_URL)) ? $next : null;
            $query = [];
            $pages++;
        }

        // Meta laat dagen zonder advertenties weg. Die vullen we aan met nullen,
        // zodat de grafiek geen gaten heeft en het aantal dagen altijd klopt.
        $rows = [];
        for ($date = $start; $date->lte($end); $date = $date->addDay()) {
            $key = $date->toDateString();
            $rows[] = ['date' => $key] + ($byDate[$key] ?? [
                'impressions' => 0,
                'clicks'      => 0,
                'conversions' => 0,
                'spend'       => 0.0,
            ]);
        }

        return $rows;
    }

    /**
     * Meta geeft conversies als lijst met soorten acties. We tellen alleen de
     * soorten mee die in config/services.php staan (meta_ads.conversion_action_types).
     */
    private function countConversions(array $actions): int
    {
        $types = config('services.meta_ads.conversion_action_types', ['lead']);
        $total = 0;

        foreach ($actions as $action) {
            if (in_array($action['action_type'] ?? null, $types, true)) {
                $total += (int) round((float) ($action['value'] ?? 0));
            }
        }

        return $total;
    }

    /**
     * Nepdata die er realistisch uitziet en altijd hetzelfde is voor
     * hetzelfde account op dezelfde dag.
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