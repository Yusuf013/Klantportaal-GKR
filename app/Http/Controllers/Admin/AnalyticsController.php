<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function index()
    {
        // Nepdata van klanten om het admin-overzicht direct te laten werken
        $clientsStats = [
            [
                'id' => 1,
                'name' => 'Bakkerij De Haan',
                'ga4_property_id' => '348102941',
                'status' => 'Actief',
                'visitors_this_month' => 3240,
                'visitors_trend' => 12.5,
                'top_page' => '/assortiment/brood',
            ],
            [
                'id' => 2,
                'name' => 'Klusservice Jansen',
                'ga4_property_id' => '392014820',
                'status' => 'Actief',
                'visitors_this_month' => 1180,
                'visitors_trend' => -4.2,
                'top_page' => '/offerte-aanvragen',
            ],
            [
                'id' => 3,
                'name' => 'Advocatenkantoor Meijer',
                'ga4_property_id' => null,
                'status' => 'Niet Gekoppeld',
                'visitors_this_month' => 0,
                'visitors_trend' => 0,
                'top_page' => '-',
            ],
        ];

        $totalOverview = [
            'total_clients' => count($clientsStats),
            'active_trackings' => 2,
            'total_combined_visitors' => array_sum(array_column($clientsStats, 'visitors_this_month')),
        ];

        return view('admin.analytics.index', compact('clientsStats', 'totalOverview'));
    }


    public function show($clientId)
    {
        // Mockdata voor de specifieke klant
        $clientData = [
            'id' => $clientId,
            'name' => 'Bakkerij De Haan',
            'domain' => 'bakkerijdehaan.nl',
            'property_id' => '348102941',
            'stats' => [
                'active_users' => 3240,
                'pageviews' => 8920,
                'avg_duration' => '2m 18s',
                'bounce_rate' => '34.2%',
            ],
            // Lijngrafiek data (afgelopen 7 dagen)
            'traffic_labels' => ['Ma 18', 'Di 19', 'Wo 20', 'Do 21', 'Vr 22', 'Za 23', 'Zo 24'],
            'traffic_data' => [420, 510, 480, 620, 780, 230, 200],
            // Herkomst donut-grafiek
            'sources' => [
                'labels' => ['Organisch (SEO)', 'Direct', 'Social Media', 'Referral'],
                'data' => [55, 25, 12, 8],
            ],
            // Top pagina's
            'top_pages' => [
                ['url' => '/assortiment/brood', 'views' => 2410, 'duration' => '1m 45s'],
                ['url' => '/contact', 'views' => 1205, 'duration' => '0m 50s'],
                ['url' => '/aanbiedingen', 'views' => 980, 'duration' => '2m 10s'],
                ['url' => '/over-ons', 'views' => 640, 'duration' => '1m 15s'],
                ['url' => '/openingstijden', 'views' => 430, 'duration' => '0m 30s'],
            ]
        ];

        return view('admin.analytics.show', compact('clientData'));
    }




}