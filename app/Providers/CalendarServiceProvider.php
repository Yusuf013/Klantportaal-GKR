<?php

namespace App\Providers;

use App\Contracts\CalendarProvider;
use App\Services\Calendar\FakeCalendarProvider;
use App\Services\Calendar\GraphCalendarProvider;
use Illuminate\Support\ServiceProvider;

/**
 * Kiest de agenda-adapter op basis van `config('calendar.driver')` (ADR-011). Standaard `fake`,
 * zodat lokaal en in CI nooit een echte Outlook-agenda wordt aangeroepen.
 */
class CalendarServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CalendarProvider::class, function () {
            if (config('calendar.driver') !== 'graph') {
                return new FakeCalendarProvider;
            }

            return new GraphCalendarProvider(
                tenantId: (string) config('calendar.graph.tenant_id'),
                clientId: (string) config('calendar.graph.client_id'),
                clientSecret: (string) config('calendar.graph.client_secret'),
                baseUrl: (string) config('calendar.graph.base_url'),
                timeoutSeconds: (int) config('calendar.graph.timeout_seconds'),
                timezone: (string) config('calendar.timezone'),
            );
        });
    }
}
