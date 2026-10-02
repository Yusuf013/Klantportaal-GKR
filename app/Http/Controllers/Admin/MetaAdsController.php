<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MetaAdsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MetaAdsController extends Controller
{
    /**
     * Overzicht voor GKR: alle gekoppelde klanten naast elkaar.
     */
    public function index(Request $request, MetaAdsService $meta)
    {
        $days = $this->period($request);

        $clients = User::where('is_admin', false)
            ->with('metaAdAccount')
            ->orderBy('name')
            ->get();

        $rows = [];
        $unlinked = 0;
        $totals = ['spend' => 0, 'conversions' => 0];

        foreach ($clients as $client) {
            if (! $client->metaAdAccount) {
                $unlinked++;
                continue;
            }

            // Gaat het bij één klant mis, dan blijft de rest van de pagina gewoon werken
            try {
                $report = $meta->getInsights($client->metaAdAccount, $days);
                $totals['spend'] += $report['totals']['spend'];
                $totals['conversions'] += $report['totals']['conversions'];
                $rows[] = ['client' => $client, 'report' => $report];
            } catch (\Throwable $e) {
                Log::error('Meta Ads ophalen mislukt (adminoverzicht)', [
                    'user_id' => $client->id,
                    'message' => $e->getMessage(),
                ]);
                $rows[] = ['client' => $client, 'report' => null];
            }
        }

        return view('admin.meta-ads.index', [
            'rows'     => $rows,
            'unlinked' => $unlinked,
            'totals'   => $totals,
            'days'     => $days,
            'isFake'   => $meta->usesFakeData(),
        ]);
    }

    /**
     * Detailpagina van één klant: precies wat de klant zelf ziet.
     */
    public function show(Request $request, User $user, MetaAdsService $meta)
    {
        // Alleen klanten met een gekoppeld account hebben een detailpagina
        abort_if($user->is_admin || ! $user->metaAdAccount, 404);

        $days = $this->period($request);
        $report = null;
        $error = null;

        try {
            $report = $meta->getInsights($user->metaAdAccount, $days);
        } catch (\Throwable $e) {
            Log::error('Meta Ads ophalen mislukt (admindetail)', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);
            $error = 'De advertentiecijfers kunnen op dit moment niet worden geladen. Kijk in de logs voor details.';
        }

        return view('admin.meta-ads.show', [
            'client' => $user,
            'report' => $report,
            'error'  => $error,
            'days'   => $days,
        ]);
    }

    private function period(Request $request): int
    {
        $days = (int) $request->query('days', MetaAdsService::DEFAULT_PERIOD);

        return in_array($days, MetaAdsService::PERIODS, true) ? $days : MetaAdsService::DEFAULT_PERIOD;
    }
}