<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Api\AvailabilityController as ClientAvailabilityController;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\MomentAvailabilityRequest;
use App\Models\Appointment;
use App\Models\User;
use App\Services\Appointments\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

/**
 * "Beschikbaarheid Jasper: Maandag 12 mei Beschikbaar / Woensdag 14 mei Bezet (conflict)".
 * Openstaande voorstellen van collega's komen terug als waarschuwing (stap 4b, regel 1).
 */
class AvailabilityController extends Controller
{
    private const LABELS = [
        AvailabilityService::STATUS_AVAILABLE => 'Beschikbaar',
        AvailabilityService::STATUS_BUSY => 'Bezet (conflict)',
        AvailabilityService::STATUS_AWAY => 'Afwezig',
        AvailabilityService::STATUS_CLOSED => 'GKR gesloten',
        AvailabilityService::STATUS_OUTSIDE_HOURS => 'Buiten werktijd',
    ];

    public function index(MomentAvailabilityRequest $request, AvailabilityService $availability): JsonResponse
    {
        $zone = config('app.timezone');
        $ids = array_map('intval', $request->validated('employee_ids'));
        $employees = User::query()->whereKey($ids)->where('is_admin', true)->orderBy('name')->get();

        $moments = array_map(function (array $m) use ($zone) {
            $dateOnly = preg_match('/^\d{4}-\d{2}-\d{2}$/', $m['start']) === 1;

            return [
                'start' => $dateOnly
                    ? CarbonImmutable::parse($m['start'], $zone)->startOfDay()
                    : CarbonImmutable::parse($m['start'])->setTimezone($zone),
                'end' => $dateOnly || empty($m['end']) ? null : CarbonImmutable::parse($m['end'])->setTimezone($zone),
            ];
        }, $request->validated('moments'));

        $result = $availability->momentStatuses(
            $employees,
            $moments,
            (int) ($request->validated('duration_minutes') ?? 60),
            (int) ($request->validated('travel_minutes') ?? 0),
            $request->validated('ignore_appointment_id') ? (int) $request->validated('ignore_appointment_id') : null,
        );

        $viewer = $request->user();

        return response()->json([
            'moments' => array_map(fn (array $moment) => [
                'start' => $moment['start'],
                'end' => $moment['end'],
                'closed_reason' => $moment['closed_reason'],
                'employees' => array_map(fn (array $row) => [
                    'id' => $row['employee']->id,
                    'name' => $row['employee']->name,
                    'color' => $row['employee']->calendarColorHex(),
                    'status' => $row['status'],
                    'label' => self::LABELS[$row['status']],
                    'warnings' => array_values(array_unique(array_map(
                        fn (array $hold) => $this->holdMessage($hold['appointment'], $viewer),
                        $row['holds'],
                    ))),
                ], $moment['employees']),
            ], $result['moments']),
            'outlook_unavailable' => $result['outlook_unavailable'],
            'notice' => $result['outlook_unavailable'] ? ClientAvailabilityController::OUTLOOK_NOTICE : null,
        ]);
    }

    private function holdMessage(Appointment $appointment, User $viewer): string
    {
        $client = $appointment->client?->name ?? 'een klant';

        if ($appointment->hasStatus(AppointmentStatus::InAfwachting)) {
            return "{$client} heeft dit moment al aangevraagd.";
        }

        if ($appointment->organizer_user_id === $viewer->id) {
            return "Je hebt dit moment al voorgesteld aan {$client}.";
        }

        $by = $appointment->organizer?->name ?? 'Een collega';

        return "{$by} heeft dit moment al voorgesteld aan {$client}.";
    }
}
