<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\GoogleAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Websitecijfers van GKR zelf. Alleen bereikbaar voor admins,
 * omdat de routes in de groep met de 'admin'-middleware staan.
 */
class AnalyticsController extends Controller
{
    public function index(Request $request, GoogleAnalyticsService $analytics)
    {
        $days = $this->period($request);
        $periods = GoogleAnalyticsService::PERIODS;

        if (! $analytics->isConfigured()) {
            return view('admin.analytics.index', [
                'data' => null,
                'days' => $days,
                'periods' => $periods,
                'error' => 'De koppeling met Google Analytics is nog niet ingesteld. Stel GA_PROPERTY_ID en de sleutel (GA_CREDENTIALS_BASE64 of GA_CREDENTIALS_PATH) in bij de omgevingsvariabelen.',
            ]);
        }

        try {
            $data = $analytics->getDashboard($days);
            $error = null;
        } catch (\Throwable $e) {
            // Volledige fout in de log; de gebruiker krijgt een korte melding.
            Log::error('Google Analytics ophalen mislukt', ['message' => $e->getMessage()]);

            $data = null;
            $error = 'De cijfers konden niet worden opgehaald bij Google Analytics. Probeer het later opnieuw. Blijft dit gebeuren, controleer dan de toegang van het service account.';
        }

        return view('admin.analytics.index', compact('data', 'error', 'days', 'periods'));
    }

    /**
     * De gekozen periode uit de URL (?periode=90).
     * Alleen waarden uit de vaste lijst worden geaccepteerd; al het andere
     * valt terug op de standaardperiode.
     */
    private function period(Request $request): int
    {
        $days = (int) $request->query('periode', GoogleAnalyticsService::DEFAULT_PERIOD);

        return array_key_exists($days, GoogleAnalyticsService::PERIODS)
            ? $days
            : GoogleAnalyticsService::DEFAULT_PERIOD;
    }

    /**
     * Leegt de cache zodat de cijfers opnieuw worden opgehaald.
     *
     * Beperkt tot 3 keer per 10 minuten, voor alle admins samen. De limieten
     * van Google gelden per property, dus niet per gebruiker; een gezamenlijke
     * limiet beschermt die het best (zie deelvraag 4).
     */
    public function refresh(Request $request, GoogleAnalyticsService $analytics)
    {
        $key = 'ga4-refresh';
        $back = route('admin.analytics.index', ['periode' => $this->period($request)]);

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);
            $unit = $minutes === 1 ? 'minuut' : 'minuten';

            return redirect($back)
                ->with('warning', "De cijfers zijn net al een paar keer vernieuwd. Probeer het over {$minutes} {$unit} opnieuw.");
        }

        RateLimiter::hit($key, 600); // 600 seconden = 10 minuten

        $analytics->clearCache();

        return redirect($back)
            ->with('success', 'De cijfers zijn opnieuw opgehaald.');
    }
}