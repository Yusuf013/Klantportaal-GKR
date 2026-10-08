<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CallbackRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Belverzoek van een klant die een afspraak op locatie wil (design "Belverzoek indienen").
 */
class CallbackRequestController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        abort_if($request->user()->isAdmin(), 403, 'Alleen klanten kunnen een belverzoek indienen.');

        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:32', 'regex:/^[0-9+()\\-\\s]{8,32}$/'],
            'note' => ['nullable', 'string', 'max:500'],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->where('user_id', $request->user()->id)],
        ], [
            'phone.regex' => 'Vul een geldig telefoonnummer in.',
        ]);

        $callback = new CallbackRequest($validated);
        $callback->user_id = $request->user()->id;
        $callback->status = CallbackRequest::STATUS_OPEN;
        $callback->save();

        return response()->json([
            'id' => $callback->id,
            'status' => $callback->status,
            'message' => 'Belverzoek ontvangen. We nemen binnen 24 uur telefonisch contact met u op om de details en de locatie te bespreken.',
        ], 201);
    }
}
