<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\GoogleAnalyticsService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Websitecijfers van GKR zelf. Alleen bereikbaar voor admins,
 * omdat de routes in de groep met de 'admin'-middleware staan.
 */
class AnalyticsController extends Controller
{
    public function index(GoogleAnalyticsService $analytics)
    {
        if (! $analytics->isConfigured()) {
            return view('admin.analytics.index', [
                'data' => null,
                'error' => 'De koppeling met Google Analytics is nog niet ingesteld. Vul GA_PROPERTY_ID en GA_CREDENTIALS_PATH in bij de omgevingsvariabelen.',
            ]);
        }

        try {
            $data = $analytics->getDashboard();
            $error = null;
        } catch (\Throwable $e) {
            // Volledige fout in de log; de gebruiker krijgt een korte melding.
            Log::error('Google Analytics ophalen mislukt', ['message' => $e->getMessage()]);

            $data = null;
            $error = 'De cijfers konden niet worden opgehaald bij Google Analytics. Probeer het later opnieuw. Blijft dit gebeuren, controleer dan de toegang van het service account.';
        }

        return view('admin.analytics.index', compact('data', 'error'));
    }

    /**
     * Leegt de cache zodat de cijfers opnieuw worden opgehaald.
     *
     * Beperkt tot 3 keer per 10 minuten, voor alle admins samen. De limieten
     * van Google gelden per property, dus niet per gebruiker; een gezamenlijke
     * limiet beschermt die het best (zie deelvraag 4).
     */
    public function refresh(GoogleAnalyticsService $analytics)
    {
        $key = 'ga4-refresh';

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);
            $unit = $minutes === 1 ? 'minuut' : 'minuten';

            return redirect()
                ->route('admin.analytics.index')
                ->with('warning', "De cijfers zijn net al een paar keer vernieuwd. Probeer het over {$minutes} {$unit} opnieuw.");
        }

        RateLimiter::hit($key, 600); // 600 seconden = 10 minuten

        $analytics->clearCache();

        return redirect()
            ->route('admin.analytics.index')
            ->with('success', 'De cijfers zijn opnieuw opgehaald.');
    }
}