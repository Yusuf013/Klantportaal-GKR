<?php

namespace App\Services;

use Google\Analytics\Data\V1beta\Client\BetaAnalyticsDataClient;
use Google\Analytics\Data\V1beta\DateRange;
use Google\Analytics\Data\V1beta\Dimension;
use Google\Analytics\Data\V1beta\Metric;
use Google\Analytics\Data\V1beta\OrderBy;
use Google\Analytics\Data\V1beta\OrderBy\DimensionOrderBy;
use Google\Analytics\Data\V1beta\OrderBy\MetricOrderBy;
use Google\Analytics\Data\V1beta\RunReportRequest;
use Google\Analytics\Data\V1beta\RunReportResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Haalt de websitecijfers van GKR op uit Google Analytics (GA4).
 *
 * - Inloggen gebeurt met een service account (zie deelvraag 2).
 * - Resultaten worden een paar uur gecachet, omdat GA4 de gegevens
 *   toch maar een paar keer per dag bijwerkt (zie deelvraag 4).
 * - De periode eindigt altijd op "gisteren", omdat de cijfers van
 *   vandaag nog niet volledig verwerkt zijn.
 * - Alleen vaste periodes zijn toegestaan (zie PERIODS), zodat er per
 *   periode maar één variant in de cache staat.
 */
class GoogleAnalyticsService
{
    /** Toegestane periodes in dagen, met het label voor de pagina. */
    public const PERIODS = [
        7 => '7 dagen',
        28 => '28 dagen',
        90 => '90 dagen',
        365 => '12 maanden',
    ];

    public const DEFAULT_PERIOD = 28;

    private ?BetaAnalyticsDataClient $client = null;

    public function isConfigured(): bool
    {
        return filled(config('services.google_analytics.property_id'))
            && filled(config('services.google_analytics.credentials'));
    }

    /**
     * Alle gegevens voor de analytics-pagina, uit de cache als die er is.
     * Gaat het ophalen mis, dan wordt er niets gecachet en komt de fout
     * bij de controller terecht.
     */
    public function getDashboard(int $days = self::DEFAULT_PERIOD): array
    {
        if (! array_key_exists($days, self::PERIODS)) {
            $days = self::DEFAULT_PERIOD;
        }

        $minutes = (int) config('services.google_analytics.cache_minutes', 180);

        return Cache::remember(
            $this->cacheKey($days),
            now()->addMinutes($minutes),
            fn () => $this->fetchDashboard($days)
        );
    }

    /** Leegt de cache voor alle periodes. */
    public function clearCache(): void
    {
        foreach (array_keys(self::PERIODS) as $days) {
            Cache::forget($this->cacheKey($days));
        }
    }

    private function cacheKey(int $days): string
    {
        return 'ga4:dashboard:' . config('services.google_analytics.property_id') . ":{$days}";
    }

    /**
     * Huidige periode: X dagen t/m gisteren.
     * Vorige periode: de X dagen daarvoor, zodat beide even lang zijn.
     */
    private function ranges(int $days): array
    {
        return [
            'current' => ["{$days}daysAgo", 'yesterday'],
            'previous' => [($days * 2) . 'daysAgo', ($days + 1) . 'daysAgo'],
        ];
    }

    private function fetchDashboard(int $days): array
    {
        $ranges = $this->ranges($days);

        $current = $this->totals(...$ranges['current']);
        $previous = $this->totals(...$ranges['previous']);

        return [
            'kpis' => [
                'active_users' => $this->kpi($current['activeUsers'], $previous['activeUsers']),
                'sessions' => $this->kpi($current['sessions'], $previous['sessions']),
                'key_events' => $this->kpi($current['keyEvents'], $previous['keyEvents']),
                'avg_session_duration' => $this->kpi($current['averageSessionDuration'], $previous['averageSessionDuration']),
                'engagement_rate' => $this->kpi($current['engagementRate'], $previous['engagementRate']),
            ],
            'daily' => $this->usersOverTime($ranges['current'], $days),
            'channels' => $this->channels($ranges['current']),
            'top_pages' => $this->topPages($ranges['current']),
            'period' => [
                'days' => $days,
                'label' => self::PERIODS[$days],
                'start' => now()->subDays($days)->toDateString(),
                'end' => now()->subDay()->toDateString(),
            ],
            'fetched_at' => now()->toIso8601String(),
        ];
    }

    /** Totalen over een periode, zonder dimensies. */
    private function totals(string $start, string $end): array
    {
        $names = ['activeUsers', 'sessions', 'keyEvents', 'averageSessionDuration', 'engagementRate'];

        $response = $this->run(
            $this->request($start, $end)->setMetrics($this->metrics($names))
        );

        $row = $response->getRows()[0] ?? null;
        $result = [];

        foreach ($names as $i => $name) {
            $result[$name] = $row ? (float) $row->getMetricValues()[$i]->getValue() : 0.0;
        }

        return $result;
    }

    /**
     * Bezoekers over tijd, voor de lijngrafiek.
     * Per dag, behalve bij 12 maanden: dan per maand, anders wordt de grafiek onleesbaar.
     */
    private function usersOverTime(array $range, int $days): array
    {
        $perMonth = $days >= 365;
        $dimension = $perMonth ? 'yearMonth' : 'date';

        $response = $this->run(
            $this->request(...$range)
                ->setDimensions([new Dimension(['name' => $dimension])])
                ->setMetrics($this->metrics(['activeUsers']))
                ->setOrderBys([new OrderBy([
                    'dimension' => new DimensionOrderBy(['dimension_name' => $dimension]),
                ])])
        );

        $labels = [];
        $values = [];

        foreach ($response->getRows() as $row) {
            $raw = $row->getDimensionValues()[0]->getValue();

            // GA4 geeft datums terug als "20260929" en maanden als "202609".
            $labels[] = $perMonth
                ? Carbon::createFromFormat('Ym|', $raw)->locale('nl')->isoFormat('MMM YYYY')
                : Carbon::createFromFormat('Ymd|', $raw)->locale('nl')->isoFormat('D MMM');
            $values[] = (int) $row->getMetricValues()[0]->getValue();
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /** Sessies per kanaal (Organic Search, Direct, Paid Social, ...). */
    private function channels(array $range): array
    {
        $response = $this->run(
            $this->request(...$range)
                ->setDimensions([new Dimension(['name' => 'sessionDefaultChannelGroup'])])
                ->setMetrics($this->metrics(['sessions']))
                ->setOrderBys([new OrderBy([
                    'metric' => new MetricOrderBy(['metric_name' => 'sessions']),
                    'desc' => true,
                ])])
        );

        $channels = [];

        foreach ($response->getRows() as $row) {
            $channels[] = [
                'name' => $row->getDimensionValues()[0]->getValue(),
                'sessions' => (int) $row->getMetricValues()[0]->getValue(),
            ];
        }

        return $channels;
    }

    /** De tien meest bekeken pagina's, met hun engagement rate. */
    private function topPages(array $range): array
    {
        $response = $this->run(
            $this->request(...$range)
                ->setDimensions([new Dimension(['name' => 'pagePath'])])
                ->setMetrics($this->metrics(['screenPageViews', 'engagementRate']))
                ->setOrderBys([new OrderBy([
                    'metric' => new MetricOrderBy(['metric_name' => 'screenPageViews']),
                    'desc' => true,
                ])])
                ->setLimit(10)
        );

        $pages = [];

        foreach ($response->getRows() as $row) {
            $pages[] = [
                'path' => $row->getDimensionValues()[0]->getValue(),
                'views' => (int) $row->getMetricValues()[0]->getValue(),
                'engagement_rate' => (float) $row->getMetricValues()[1]->getValue(),
            ];
        }

        return $pages;
    }

    // ----- Hulpfuncties -------------------------------------------------

    private function request(string $start, string $end): RunReportRequest
    {
        return (new RunReportRequest())
            ->setProperty('properties/' . config('services.google_analytics.property_id'))
            ->setDateRanges([new DateRange(['start_date' => $start, 'end_date' => $end])]);
    }

    private function metrics(array $names): array
    {
        return array_map(fn ($name) => new Metric(['name' => $name]), $names);
    }

    private function run(RunReportRequest $request): RunReportResponse
    {
        return $this->client()->runReport($request);
    }

    private function client(): BetaAnalyticsDataClient
    {
        return $this->client ??= new BetaAnalyticsDataClient([
            'credentials' => config('services.google_analytics.credentials'),
            'transport' => 'rest',
        ]);
    }

    /** Waarde + procentuele verandering t.o.v. de vorige periode. */
    private function kpi(float $current, float $previous): array
    {
        return [
            'value' => $current,
            'change' => $previous > 0 ? round((($current - $previous) / $previous) * 100, 1) : null,
        ];
    }
}