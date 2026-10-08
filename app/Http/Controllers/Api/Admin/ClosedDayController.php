<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClosedDay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Dagen waarop heel GKR dicht is (feestdagen, kerstsluiting). Stap 4a, ADR-011.
 */
class ClosedDayController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            ClosedDay::query()->whereDate('date', '>=', now()->subMonth()->toDateString())->orderBy('date')->get()
                ->map(fn (ClosedDay $d) => $this->present($d)),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'reason' => ['required', 'string', 'max:100'],
        ]);

        if (ClosedDay::query()->whereDate('date', $validated['date'])->exists()) {
            return response()->json(['message' => 'Deze dag staat al als gesloten.', 'errors' => ['date' => ['Deze dag staat al als gesloten.']]], 422);
        }

        return response()->json($this->present(ClosedDay::create($validated)), 201);
    }

    public function destroy(ClosedDay $closedDay): Response
    {
        $closedDay->delete();

        return response()->noContent();
    }

    /**
     * @return array{id: int, date: string, reason: string}
     */
    private function present(ClosedDay $day): array
    {
        return ['id' => $day->id, 'date' => $day->date->toDateString(), 'reason' => $day->reason];
    }
}
