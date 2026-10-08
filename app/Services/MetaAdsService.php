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
 *
 * Het rapport heeft dezelfde opbouw als dat van GoogleAdsService, inclusief
 * 'campaigns': de cijfers per campagne over de gekozen periode.
 *
 * De totalen komen van het hele account (zoals getest tegen Ads Manager).
 * De campagnes halen we met een aparte, kleine aanvraag op: één rij per campagne.
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

    // Namen voor de nepdata. Echte campagnenamen komen uit Meta.
    private const FAKE_CAMPAIGNS = [
        'Leads - Nieuwe doelgroep',
        'Leads - Remarketing',
        'Naamsbekendheid - Video',
        'Verkeer - Website',
        'Remarketing - Websitebezoekers',
    ];

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
        // het omschakelen nooit nog oude nepcijfers ziet.
        // "v2": sinds de campagnes erbij zitten heeft het rapport een extra onderdeel.
        // Door het versienummer worden oude rapporten (zonder campagnes) niet meer gebruikt.
        $mode = $this->usesFakeData() ? 'fake' : 'live';
        $cacheKey = "meta_ads:v2:{$mode}:{$account->account_id}:{$days}:{$end->toDateString()}";

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
            // Cijfers per campagne over de huidige periode, hoogste besteding bovenaan
            'campaigns' => $this->fetchCampaigns($accountId, $start, $end, $totals),
        ];
    }

    /**
     * Cijfers per campagne: name, impressions, clicks, ctr, conversions, spend.
     */
    private function fetchCampaigns(string $accountId, CarbonImmutable $start, CarbonImmutable $end, array $totals): array
    {
        $campaigns = $this->usesFakeData()
            ? $this->fakeCampaigns($accountId, $totals)
            : $this->liveCampaigns($accountId, $start, $end);

        usort($campaigns, fn ($a, $b) => $b['spend'] <=> $a['spend']);

        return $campaigns;
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
        $rows = $this->insightRows($accountId, [
            'level'          => 'account',
            'fields'         => 'impressions,clicks,spend,actions',
            'time_range'     => json_encode(['since' => $start->toDateString(), 'until' => $end->toDateString()]),
            'time_increment' => 1,   // één rij per dag
            'limit'          => 100,
        ]);

        $byDate = [];

        foreach ($rows as $row) {
            if (! isset($row['date_start'])) {
                continue;
            }

            $byDate[$row['date_start']] = [
                'impressions' => (int) ($row['impressions'] ?? 0),
                'clicks'      => (int) ($row['clicks'] ?? 0),
                'conversions' => $this->countConversions($row['actions'] ?? []),
                'spend'       => round((float) ($row['spend'] ?? 0), 2),
            ];
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
     * Echte cijfers per campagne over de hele periode.
     *
     * We vragen hier geen cijfers per dag: Meta geeft dan één rij per campagne.
     * Dat blijft klein, ook bij 12 maanden.
     */
    private function liveCampaigns(string $accountId, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $rows = $this->insightRows($accountId, [
            'level'      => 'campaign',
            'fields'     => 'campaign_id,campaign_name,impressions,clicks,spend,actions',
            'time_range' => json_encode(['since' => $start->toDateString(), 'until' => $end->toDateString()]),
            'limit'      => 100,
        ]);

        $campaigns = [];

        foreach ($rows as $row) {
            $impressions = (int) ($row['impressions'] ?? 0);
            $clicks = (int) ($row['clicks'] ?? 0);
            $spend = round((float) ($row['spend'] ?? 0), 2);

            // Campagnes die in deze periode niets hebben gedaan, laten we weg
            if ($impressions === 0 && $spend == 0.0) {
                continue;
            }

            $campaigns[] = [
                'name'        => (string) ($row['campaign_name'] ?? 'Campagne zonder naam'),
                'impressions' => $impressions,
                'clicks'      => $clicks,
                // Zelf uitgerekend, zodat het altijd klopt met de getoonde klikken en vertoningen
                'ctr'         => $impressions > 0 ? round($clicks / $impressions * 100, 2) : 0.0,
                'conversions' => $this->countConversions($row['actions'] ?? []),
                'spend'       => $spend,
            ];
        }

        return $campaigns;
    }

    /**
     * Haalt alle rijen van één Insights-aanvraag op en bladert door de pagina's.
     * Gedeeld door de cijfers per dag en de cijfers per campagne.
     */
    private function insightRows(string $accountId, array $query): array
    {
        $token = config('services.meta_ads.access_token');
        $version = config('services.meta_ads.api_version');

        if (blank($token) || blank($version)) {
            throw new RuntimeException('Meta-koppeling is niet ingesteld: token of API-versie ontbreekt.');
        }

        $url = self::GRAPH_URL . "{$version}/act_{$accountId}/insights";
        $rows = [];
        $pages = 0;

        while ($url) {
            // Liever een foutmelding dan cijfers die ongemerkt onvolledig zijn
            if ($pages >= self::MAX_PAGES) {
                throw new RuntimeException('Meta gaf meer gegevens dan verwacht. De cijfers zouden onvolledig zijn en worden daarom niet getoond.');
            }

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

            foreach ((array) $response->json('data', []) as $row) {
                $rows[] = $row;
            }

            // Volgende pagina? Die link bevat alle parameters al.
            // Alleen volgen als hij echt naar Meta wijst, zodat de token nooit ergens anders heen gaat.
            $next = $response->json('paging.next');
            $url = (is_string($next) && str_starts_with($next, self::GRAPH_URL)) ? $next : null;
            $query = [];
            $pages++;
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

    /**
     * Nep-campagnes voor demo's: 2 tot 4 vaste campagnes per account.
     * Ze verdelen de totalen van de periode, zodat de tabel altijd optelt
     * tot de cijfers bovenaan de pagina.
     */
    private function fakeCampaigns(string $accountId, array $totals): array
    {
        $random = new Randomizer(new Mt19937(crc32('campaigns|' . $accountId)));
        $names = array_slice($random->shuffleArray(self::FAKE_CAMPAIGNS), 0, $random->getInt(2, 4));

        // Elke campagne krijgt een vast aandeel, per cijfer een klein beetje anders.
        // Zo hebben de campagnes niet allemaal precies dezelfde klikratio.
        $shares = array_map(fn () => $random->getInt(10, 40), $names);

        $impressions = $this->split($totals['impressions'], $shares, $random);
        $clicks = $this->split($totals['clicks'], $shares, $random);
        $conversions = $this->split($totals['conversions'], $shares, $random);
        // Bedragen verdelen we in centen, zodat er geen cent verloren gaat
        $spendCents = $this->split((int) round($totals['spend'] * 100), $shares, $random);

        $campaigns = [];

        foreach ($names as $index => $name) {
            if ($impressions[$index] === 0) {
                continue;
            }

            $campaigns[] = [
                'name'        => $name,
                'impressions' => $impressions[$index],
                'clicks'      => $clicks[$index],
                'ctr'         => round($clicks[$index] / $impressions[$index] * 100, 2),
                'conversions' => $conversions[$index],
                'spend'       => $spendCents[$index] / 100,
            ];
        }

        return $campaigns;
    }

    /**
     * Verdeelt een heel getal over de aandelen. De laatste krijgt de rest,
     * zodat de som altijd precies het totaal is.
     */
    private function split(int $total, array $shares, Randomizer $random): array
    {
        $weights = array_map(fn ($share) => $share * $random->getInt(80, 120), $shares);
        $sum = array_sum($weights);
        $last = array_key_last($weights);

        $parts = [];
        $used = 0;

        foreach ($weights as $index => $weight) {
            $parts[$index] = $index === $last ? $total - $used : intdiv($total * $weight, $sum);
            $used += $parts[$index];
        }

        return $parts;
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