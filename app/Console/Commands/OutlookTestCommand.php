<?php

namespace App\Console\Commands;

use App\Services\OutlookCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class OutlookTestCommand extends Command
{
    protected $signature = 'outlook:test {--days=14 : Aantal dagen vooruit (1 t/m 60)}';

    protected $description = 'Test het lezen van een gepubliceerde Outlook-agenda (de link staat in .env als OUTLOOK_TEST_ICS_URL)';

    private const WEEKDAYS = ['zo', 'ma', 'di', 'wo', 'do', 'vr', 'za'];

    public function handle(OutlookCalendar $outlook): int
    {
        // De link komt bewust uit .env en niet uit het commando zelf:
        // wat je in de terminal typt, blijft in de geschiedenis van je terminal staan.
        $url = config('services.outlook.test_ics_url');

        if (blank($url)) {
            $this->error('Zet eerst OUTLOOK_TEST_ICS_URL in je .env en draai daarna: php artisan config:clear');

            return self::FAILURE;
        }

        $days = max(1, min(60, (int) $this->option('days')));
        $from = CarbonImmutable::today(OutlookCalendar::TIMEZONE);
        $to = $from->addDays($days);

        try {
            $intervals = $outlook->busyIntervals($url, $from, $to);
        } catch (\Throwable $e) {
            // De meldingen van OutlookCalendar bevatten nooit de link
            $this->error('Ophalen mislukt: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info("Bezette momenten van {$from->format('d-m-Y')} t/m {$to->subDay()->format('d-m-Y')}: " . count($intervals));

        $rows = [];
        foreach ($intervals as $interval) {
            $start = $interval['start'];
            $end = $interval['end'];

            $wholeDays = $start->format('H:i') === '00:00' && $end->format('H:i') === '00:00';
            $sameDay = $start->format('Y-m-d') === $end->format('Y-m-d');

            $rows[] = [
                self::WEEKDAYS[(int) $start->format('w')] . ' ' . $start->format('d-m'),
                $wholeDays ? 'hele dag' : $start->format('H:i'),
                match (true) {
                    $wholeDays => 't/m ' . $end->subDay()->format('d-m'),
                    $sameDay   => $end->format('H:i'),
                    default    => $end->format('d-m H:i'),
                },
            ];
        }

        $this->table(['Dag', 'Van', 'Tot'], $rows);
        $this->line('Deze uitkomst wordt ' . (int) config('services.outlook.cache_minutes', 5) . ' minuten onthouden.');

        return self::SUCCESS;
    }
}