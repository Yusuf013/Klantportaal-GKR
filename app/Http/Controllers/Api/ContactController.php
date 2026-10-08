<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Appointments\AvailabilityService;
use App\Services\Appointments\WorkingHours;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

/**
 * "Bel direct": telefoonnummer en of GKR nu bereikbaar is (werktijd, geen sluitingsdag).
 */
class ContactController extends Controller
{
    public function show(WorkingHours $hours, AvailabilityService $availability): JsonResponse
    {
        $phone = config('appointments.contact.phone');
        $now = CarbonImmutable::now();
        [$open, $close] = $hours->window($now);

        $availableNow = $phone !== null
            && $hours->isWorkingDay($now)
            && $now->between($open, $close)
            && $availability->closedReason($now) === null;

        return response()->json([
            'phone' => $phone,
            'available_now' => $availableNow,
            'label' => $availableNow ? 'Nu beschikbaar' : 'Nu niet beschikbaar',
        ]);
    }
}
