<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdAccount;
use App\Models\User;
use App\Services\GoogleAdsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Koppelt een klant aan een Google Ads-account.
 * Zelfde opzet als MetaAccountController.
 */
class GoogleAdsAccountController extends Controller
{
    public function update(Request $request, User $user)
    {
        // Alleen klanten kunnen een advertentieaccount krijgen, geen admins
        abort_if($user->is_admin, 404);

        // Admin mag "123-456-7890" of "1234567890" invullen; we bewaren alleen de cijfers
        $request->merge([
            'google_ads_customer_id' => $request->filled('google_ads_customer_id')
                ? GoogleAdsService::normalizeCustomerId($request->input('google_ads_customer_id'))
                : null,
        ]);

        $validated = $request->validate([
            'google_ads_customer_id' => [
                'nullable',
                // Een Google Ads-klantnummer is altijd precies 10 cijfers
                'regex:/^\d{10}$/',
                // Mag niet al bij een ANDERE klant horen (eigen koppeling telt niet mee)
                Rule::unique('ad_accounts', 'account_id')
                    ->where('platform', AdAccount::PLATFORM_GOOGLE_ADS)
                    ->ignore($user->id, 'user_id'),
            ],
        ], [
            'google_ads_customer_id.regex'  => 'Een Google Ads-klantnummer bestaat uit 10 cijfers, bijv. 123-456-7890.',
            'google_ads_customer_id.unique' => 'Dit Google Ads-account is al aan een andere klant gekoppeld.',
        ]);

        $newId = $validated['google_ads_customer_id'];
        $current = $user->googleAdsAccount;

        // Niets veranderd? Dan ook niets opslaan, zodat de koppeldatum klopt
        if ($current?->account_id === $newId) {
            return back()->with('success', "Er is niets gewijzigd bij {$user->name}.");
        }

        // Oude koppeling weg, nieuwe erin. Alles of niets.
        // Let op: alleen het Google Ads-account, het Meta-account blijft staan.
        DB::transaction(function () use ($user, $current, $newId) {
            $current?->delete();

            if ($newId) {
                $user->adAccounts()->create([
                    'platform'   => AdAccount::PLATFORM_GOOGLE_ADS,
                    'account_id' => $newId,
                ]);
            }
        });

        return back()->with('success', $newId
            ? "Google Ads-account gekoppeld aan {$user->name}."
            : "Google Ads-koppeling verwijderd bij {$user->name}.");
    }
}