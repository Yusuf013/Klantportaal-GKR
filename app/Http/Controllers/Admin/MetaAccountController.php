<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdAccount;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MetaAccountController extends Controller
{
    public function update(Request $request, User $user)
    {
        // Alleen klanten kunnen een advertentieaccount krijgen, geen admins
        abort_if($user->is_admin, 404);

        // Admin mag "act_123" of "123" invullen; we bewaren alleen de cijfers
        $request->merge([
            'meta_ad_account_id' => $request->filled('meta_ad_account_id')
                ? preg_replace('/^act_/i', '', trim($request->input('meta_ad_account_id')))
                : null,
        ]);

        $validated = $request->validate([
            'meta_ad_account_id' => [
                'nullable',
                'regex:/^\d{5,20}$/',
                // Mag niet al bij een ANDERE klant horen (eigen koppeling telt niet mee)
                Rule::unique('ad_accounts', 'account_id')
                    ->where('platform', AdAccount::PLATFORM_META)
                    ->ignore($user->id, 'user_id'),
            ],
        ], [
            'meta_ad_account_id.regex'  => 'Een advertentieaccount-ID bestaat alleen uit cijfers (eventueel met "act_" ervoor).',
            'meta_ad_account_id.unique' => 'Dit advertentieaccount is al aan een andere klant gekoppeld.',
        ]);

        $newId = $validated['meta_ad_account_id'];
        $current = $user->metaAdAccount;

        // Niets veranderd? Dan ook niets opslaan, zodat de koppeldatum klopt
        if ($current?->account_id === $newId) {
            return back()->with('success', "Er is niets gewijzigd bij {$user->name}.");
        }

        // Oude koppeling weg, nieuwe erin. Alles of niets.
        DB::transaction(function () use ($user, $current, $newId) {
            $current?->delete();

            if ($newId) {
                $user->adAccounts()->create([
                    'platform'   => AdAccount::PLATFORM_META,
                    'account_id' => $newId,
                ]);
            }
        });

        return back()->with('success', $newId
            ? "Meta-advertentieaccount gekoppeld aan {$user->name}."
            : "Meta-koppeling verwijderd bij {$user->name}.");
    }
}