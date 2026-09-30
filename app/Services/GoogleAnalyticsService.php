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
 */
class GoogleAnalyticsService
{
    private const CURRENT = ['28daysAgo', 'yesterday'];
    private const PREVIOUS = ['56daysAgo', '29daysAgo'];

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
    public function getDashboard(): array
    {
        $propertyId = config('services.google_analytics.property_id');
        $minutes = (int) config('services.google_analytics.cache_minutes', 180);

        return Cache::remember(
            "ga4:dashboard:{$propertyId}",
            now()->addMinutes($minutes),
            fn () => $this->fetchDashboard()
        );
    }

    public function clearCache(): void
    {
        Cache::forget('ga4:dashboard:' . config('services.google_analytics.property_id'));
    }

    private function fetchDashboard(): array
    {
        $current = $this->totals(...self::CURRENT);
        $previous = $this->totals(...self::PREVIOUS);

        return [
            'kpis' => [
                'active_users' => $this->kpi($current['activeUsers'], $previous['activeUsers']),
                'sessions' => $this->kpi($current['sessions'], $previous['sessions']),
                'key_events' => $this->kpi($current['keyEvents'], $previous['keyEvents']),
                'avg_session_duration' => $this->kpi($current['averageSessionDuration'], $previous['averageSessionDuration']),
                'engagement_rate' => $this->kpi($current['engagementRate'], $previous['engagementRate']),
            ],
            'daily' => $this->dailyUsers(),
            'channels' => $this->channels(),
            'top_pages' => $this->topPages(),
            'period' => [
                'start' => now()->subDays(28)->toDateString(),
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

    /** Bezoekers per dag, voor de lijngrafiek. */
    private function dailyUsers(): array
    {
        $response = $this->run(
            $this->request(...self::CURRENT)
                ->setDimensions([new Dimension(['name' => 'date'])])
                ->setMetrics($this->metrics(['activeUsers']))
                ->setOrderBys([new OrderBy([
                    'dimension' => new DimensionOrderBy(['dimension_name' => 'date']),
                ])])
        );

        $labels = [];
        $values = [];

        foreach ($response->getRows() as $row) {
            // GA4 geeft datums terug als "20260929".
            $labels[] = Carbon::createFromFormat('Ymd', $row->getDimensionValues()[0]->getValue())
                ->locale('nl')->isoFormat('D MMM');
            $values[] = (int) $row->getMetricValues()[0]->getValue();
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /** Sessies per kanaal (Organic Search, Direct, Paid Social, ...). */
    private function channels(): array
    {
        $response = $this->run(
            $this->request(...self::CURRENT)
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
    private function topPages(): array
    {
        $response = $this->run(
            $this->request(...self::CURRENT)
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