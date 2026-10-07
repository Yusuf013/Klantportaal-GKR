<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\GoogleAdsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GoogleAdsController extends Controller
{
    /**
     * Overzicht voor GKR: alle klanten met een Google Ads-koppeling naast elkaar.
     */
    public function index(Request $request, GoogleAdsService $googleAds)
    {
        $days = $this->period($request);

        $clients = User::where('is_admin', false)
            ->with('googleAdsAccount')
            ->orderBy('name')
            ->get();

        $rows = [];
        $unlinked = 0;
        $totals = ['spend' => 0, 'conversions' => 0];

        foreach ($clients as $client) {
            if (! $client->googleAdsAccount) {
                $unlinked++;
                continue;
            }

            // Gaat het bij één klant mis, dan blijft de rest van de pagina gewoon werken
            try {
                $report = $googleAds->getInsights($client->googleAdsAccount, $days);
                $totals['spend'] += $report['totals']['spend'];
                $totals['conversions'] += $report['totals']['conversions'];
                $rows[] = ['client' => $client, 'report' => $report];
            } catch (\Throwable $e) {
                Log::error('Google Ads ophalen mislukt (adminoverzicht)', [
                    'user_id' => $client->id,
                    'message' => $e->getMessage(),
                ]);
                $rows[] = ['client' => $client, 'report' => null];
            }
        }

        return view('admin.google-ads.index', [
            'rows'     => $rows,
            'unlinked' => $unlinked,
            'totals'   => $totals,
            'days'     => $days,
            'isFake'   => $googleAds->usesFakeData(),
        ]);
    }

    /**
     * Detailpagina van één klant: wat de klant zelf ziet, plus de cijfers per campagne.
     */
    public function show(Request $request, User $user, GoogleAdsService $googleAds)
    {
        // Alleen klanten met een gekoppeld account hebben een detailpagina
        abort_if($user->is_admin || ! $user->googleAdsAccount, 404);

        $days = $this->period($request);
        $report = null;
        $error = null;

        try {
            $report = $googleAds->getInsights($user->googleAdsAccount, $days);
        } catch (\Throwable $e) {
            Log::error('Google Ads ophalen mislukt (admindetail)', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);
            $error = 'De advertentiecijfers kunnen op dit moment niet worden geladen. Kijk in de logs voor details.';
        }

        return view('admin.google-ads.show', [
            'client' => $user,
            'report' => $report,
            'error'  => $error,
            'days'   => $days,
        ]);
    }

    private function period(Request $request): int
    {
        $days = (int) $request->query('days', GoogleAdsService::DEFAULT_PERIOD);

        return in_array($days, GoogleAdsService::PERIODS, true) ? $days : GoogleAdsService::DEFAULT_PERIOD;
    }
}