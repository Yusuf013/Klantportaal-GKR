<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Voorkeuren van de ingelogde medewerker: "Mijn afspraken" of "Iedereen" (stap 4c). Dezelfde
 * kolom als het vinkje op de website, dus de keuze geldt op beide.
 */
class PreferenceController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate(['agenda_scope' => ['required', 'in:mine,all']]);

        $request->user()->forceFill($validated)->save();

        return response()->json(['agenda_scope' => $validated['agenda_scope']]);
    }
}
