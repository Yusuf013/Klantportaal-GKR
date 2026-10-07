<?php

namespace App\Http\Controllers;

use App\Services\GoogleAdsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GoogleAdsController extends Controller
{
    /**
     * Klantpagina: de ingelogde klant ziet ALLEEN de cijfers van het eigen account.
     * Er zit bewust geen account- of klant-ID in de URL: het account komt
     * altijd van auth()->user(), dus een klant kan niets anders opvragen.
     */
    public function index(Request $request, GoogleAdsService $googleAds)
    {
        $account = $request->user()->googleAdsAccount;
        $days = $this->period($request);

        $report = null;
        $error = null;

        if ($account) {
            try {
                $report = $googleAds->getInsights($account, $days);
            } catch (\Throwable $e) {
                // Technische details naar de log, nette melding naar de klant
                Log::error('Google Ads ophalen mislukt (klantpagina)', [
                    'user_id' => $request->user()->id,
                    'message' => $e->getMessage(),
                ]);
                $error = 'De advertentiecijfers kunnen op dit moment niet worden geladen. Probeer het later opnieuw.';
            }
        }

        return view('google-ads.index', compact('account', 'report', 'error', 'days'));
    }

    private function period(Request $request): int
    {
        $days = (int) $request->query('days', GoogleAdsService::DEFAULT_PERIOD);

        return in_array($days, GoogleAdsService::PERIODS, true) ? $days : GoogleAdsService::DEFAULT_PERIOD;
    }
}