<?php

namespace App\Http\Controllers;

use App\Services\MetaAdsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MetaAdsController extends Controller
{
    /**
     * Klantpagina: de ingelogde klant ziet ALLEEN de cijfers van het eigen account.
     * Er zit bewust geen account- of klant-ID in de URL: het account komt
     * altijd van auth()->user(), dus een klant kan niets anders opvragen.
     */
    public function index(Request $request, MetaAdsService $meta)
    {
        $account = $request->user()->metaAdAccount;
        $days = $this->period($request);

        $report = null;
        $error = null;

        if ($account) {
            try {
                $report = $meta->getInsights($account, $days);
            } catch (\Throwable $e) {
                // Technische details naar de log, nette melding naar de klant
                Log::error('Meta Ads ophalen mislukt (klantpagina)', [
                    'user_id' => $request->user()->id,
                    'message' => $e->getMessage(),
                ]);
                $error = 'De advertentiecijfers kunnen op dit moment niet worden geladen. Probeer het later opnieuw.';
            }
        }

        return view('meta-ads.index', compact('account', 'report', 'error', 'days'));
    }

    private function period(Request $request): int
    {
        $days = (int) $request->query('days', MetaAdsService::DEFAULT_PERIOD);

        return in_array($days, MetaAdsService::PERIODS, true) ? $days : MetaAdsService::DEFAULT_PERIOD;
    }
}