<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AvailabilityRequest;
use App\Models\User;
use App\Services\Appointments\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

/**
 * Vrije blokken voor de datumkiezer ("Beschikbare dagen van York en Noah"). Een klant ziet
 * alleen óf er tijd is, nooit waarom niet (geen vakanties of andermans afspraken).
 */
class AvailabilityController extends Controller
{
    public const OUTLOOK_NOTICE = 'We konden de Outlook-agenda nu niet controleren; tijden kunnen nog bezet blijken.';

    public function index(AvailabilityRequest $request, AvailabilityService $availability): JsonResponse
    {
        $ids = array_map('intval', $request->validated('employee_ids'));
        $employees = User::query()->whereKey($ids)->where('is_admin', true)->get();
        $zone = config('app.timezone');

        $result = $availability->slots(
            $employees,
            CarbonImmutable::parse($request->validated('from'), $zone),
            CarbonImmutable::parse($request->validated('to'), $zone),
            $request->durationMinutes(),
            $request->travelMinutes(),
        );

        return response()->json([
            'employees' => $employees->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])->values(),
            'duration_minutes' => $request->durationMinutes(),
            'days' => array_map(fn (array $day) => [
                'date' => $day['date'],
                'closed_reason' => $day['closed_reason'],
                'slots' => array_map(fn (array $slot) => ['start' => $slot['start'], 'end' => $slot['end']], $day['slots']),
            ], $result['days']),
            'outlook_unavailable' => $result['outlook_unavailable'],
            'notice' => $result['outlook_unavailable'] ? self::OUTLOOK_NOTICE : null,
        ]);
    }
}
