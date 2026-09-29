<?php

// Plaats dit bestand in: app/Console/Commands/GaTestCommand.php
// Uitvoeren met:        php artisan ga:test 123456789

namespace App\Console\Commands;

use Google\Analytics\Data\V1beta\Client\BetaAnalyticsDataClient;
use Google\Analytics\Data\V1beta\DateRange;
use Google\Analytics\Data\V1beta\Metric;
use Google\Analytics\Data\V1beta\RunReportRequest;
use Illuminate\Console\Command;

class GaTestCommand extends Command
{
    protected $signature = 'ga:test {propertyId : Het GA4 property ID van de testwebsite}';

    protected $description = 'Prototype: haalt een paar basiscijfers op uit Google Analytics';

    public function handle(): int
    {
        $propertyId = $this->argument('propertyId');

        // Het pad naar de sleutel komt uit .env, zodat het niet in de code staat.
        $client = new BetaAnalyticsDataClient([
            'credentials' => env('GA_CREDENTIALS_PATH'),
            'transport'   => 'rest', // werkt zonder extra PHP-extensie (gRPC)
        ]);

        // Zelfde periode als het standaardrapport in GA4: laatste 28 dagen, zonder vandaag.
        $request = (new RunReportRequest())
            ->setProperty('properties/' . $propertyId)
            ->setDateRanges([
                new DateRange(['start_date' => '28daysAgo', 'end_date' => 'yesterday']),
            ])
            ->setMetrics([
                new Metric(['name' => 'activeUsers']),
                new Metric(['name' => 'sessions']),
                new Metric(['name' => 'keyEvents']),
            ]);

        try {
            $response = $client->runReport($request);
        } catch (\Throwable $e) {
            $this->error('Aanvraag mislukt: ' . $e->getMessage());
            return self::FAILURE;
        }

        $row = $response->getRows()[0] ?? null;

        if ($row === null) {
            $this->warn('Geen data gevonden voor deze periode.');
            return self::SUCCESS;
        }

        $values = $row->getMetricValues();

        $this->info("Property {$propertyId} – laatste 28 dagen");
        $this->table(['Cijfer', 'Waarde'], [
            ['Bezoekers (activeUsers)', $values[0]->getValue()],
            ['Sessies (sessions)', $values[1]->getValue()],
            ['Conversies (keyEvents)', $values[2]->getValue()],
        ]);

        return self::SUCCESS;
    }
}