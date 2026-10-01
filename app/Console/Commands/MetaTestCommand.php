<?php

namespace App\Console\Commands;

use App\Models\AdAccount;
use App\Services\MetaAdsService;
use Illuminate\Console\Command;

class MetaTestCommand extends Command
{
    protected $signature = 'meta:test {accountId : Het advertentieaccount, bijv. act_123456789} {--days=28 : 7, 28, 90 of 365}';

    protected $description = 'Test de Meta Ads-service (nepdata of echt) voor één advertentieaccount';

    public function handle(MetaAdsService $meta): int
    {
        // Tijdelijk account, wordt NIET opgeslagen in de database
        $account = new AdAccount([
            'platform'   => AdAccount::PLATFORM_META,
            'account_id' => preg_replace('/^act_/i', '', $this->argument('accountId')),
        ]);

        try {
            $report = $meta->getInsights($account, (int) $this->option('days'));
        } catch (\Throwable $e) {
            $this->error('Ophalen mislukt: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info(($report['is_fake'] ? '[NEPDATA] ' : '[LIVE] ') . "Periode {$report['start']} t/m {$report['end']} ({$report['days']} dagen)");

        $rows = [];
        foreach ($report['totals'] as $metric => $value) {
            $trend = $report['trends'][$metric];
            $rows[] = [$metric, $value, $report['previous'][$metric], $trend === null ? '-' : $trend . '%'];
        }

        $this->table(['Cijfer', 'Deze periode', 'Vorige periode', 'Trend'], $rows);
        $this->line('Aantal punten in de grafiek: ' . count($report['series']));

        return self::SUCCESS;
    }
}