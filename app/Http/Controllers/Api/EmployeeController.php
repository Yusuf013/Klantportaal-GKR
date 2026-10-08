<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * GKR-medewerkers voor "Selecteer GKR medewerker" en de kleuren in de admin-kalender.
 */
class EmployeeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $employees = User::query()->where('is_admin', true)->orderBy('name')->get();

        return response()->json(EmployeeResource::collection($employees)->resolve($request));
    }

    /**
     * Admin kiest een andere agendakleur voor een medewerker (ADR-011).
     */
    public function update(Request $request, User $employee): EmployeeResource
    {
        abort_unless($employee->isAdmin(), 404);

        $validated = $request->validate([
            'calendar_color' => ['required', 'string', Rule::in(array_keys(config('calendar.colors')))],
        ]);

        $employee->forceFill(['calendar_color' => $validated['calendar_color']])->save();

        return new EmployeeResource($employee);
    }
}
