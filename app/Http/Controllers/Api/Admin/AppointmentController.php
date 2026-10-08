<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Api\AppointmentController as ClientAppointmentController;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\StoreAppointmentProposalRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Services\Appointments\AppointmentService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Afspraken beheren als GKR-medewerker (FR-08, ADR-011). Alleen via de `admin`-middleware.
 */
class AppointmentController extends Controller
{
    public function __construct(private readonly AppointmentService $appointments) {}

    /**
     * "Mijn afspraken" of "Iedereen" (stap 4c). Zonder `scope` geldt de opgeslagen keuze.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'scope' => ['nullable', 'in:mine,all'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $user = $request->user();
        $scope = $validated['scope'] ?? $user->agenda_scope ?? 'all';
        $zone = config('app.timezone');

        $appointments = Appointment::query()
            ->where('status', '!=', AppointmentStatus::Geannuleerd->value)
            ->when($scope === 'mine', fn ($q) => $q->forEmployee($user))
            ->when($validated['from'] ?? null, fn ($q, $from) => $q->where('end_time', '>=', CarbonImmutable::parse($from, $zone)->startOfDay()))
            ->when($validated['to'] ?? null, fn ($q, $to) => $q->where('start_time', '<=', CarbonImmutable::parse($to, $zone)->endOfDay()))
            ->with(ClientAppointmentController::RELATIONS)
            ->orderBy('start_time')
            ->get();

        return response()->json([
            'scope' => $scope,
            'appointments' => AppointmentResource::collection($appointments)->resolve($request),
        ]);
    }

    public function store(StoreAppointmentProposalRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $zone = config('app.timezone');

        $appointment = $this->appointments->proposeByAdmin($request->user(), [
            'client_id' => (int) $validated['client_id'],
            'project_id' => (int) $validated['project_id'],
            'type' => $validated['type'],
            'location' => $validated['location'] ?? null,
            'travel_minutes' => isset($validated['travel_minutes']) ? (int) $validated['travel_minutes'] : null,
            'duration_minutes' => (int) $validated['duration_minutes'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'employee_ids' => array_map('intval', $validated['employee_ids'] ?? []),
            'options' => array_map(fn ($o) => CarbonImmutable::parse($o)->setTimezone($zone), $validated['options']),
        ]);

        return (new AppointmentResource($appointment->load(ClientAppointmentController::RELATIONS)))
            ->response()
            ->setStatusCode(201);
    }

    public function approve(Request $request, Appointment $appointment): JsonResponse
    {
        Gate::authorize('manage', $appointment);

        $result = $this->appointments->approve($appointment);

        return response()->json([
            'appointment' => (new AppointmentResource($result['appointment']->load(ClientAppointmentController::RELATIONS)))->resolve($request),
            'notice' => $result['outlook_checked']
                ? null
                : 'We konden de Outlook-agenda nu niet controleren. Controleer zelf of het moment nog vrij is.',
        ]);
    }

    public function reject(Appointment $appointment): AppointmentResource
    {
        Gate::authorize('manage', $appointment);

        return new AppointmentResource($this->appointments->reject($appointment)->load(ClientAppointmentController::RELATIONS));
    }
}
