<?php

namespace App\Http\Controllers\Api;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreAppointmentRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Services\Appointments\AppointmentService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Afspraken van de ingelogde klant (FR-08, ADR-011). Scoping server-side op `user_id`; een
 * afspraak van iemand anders geeft 404 (AppointmentPolicy).
 */
class AppointmentController extends Controller
{
    public const RELATIONS = ['project:id,name', 'client:id,name', 'organizer', 'attendees', 'options'];

    public function __construct(private readonly AppointmentService $appointments) {}

    public function index(Request $request): JsonResponse
    {
        $appointments = Appointment::query()
            ->where('user_id', $request->user()->id)
            ->where('status', '!=', AppointmentStatus::Geannuleerd->value)
            ->with(self::RELATIONS)
            ->orderBy('start_time')
            ->get();

        return response()->json(AppointmentResource::collection($appointments)->resolve($request));
    }

    public function show(Appointment $appointment): AppointmentResource
    {
        Gate::authorize('view', $appointment);

        return new AppointmentResource($appointment->load(self::RELATIONS));
    }

    public function store(StoreAppointmentRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $appointment = $this->appointments->requestByClient($request->user(), [
            'project_id' => (int) $validated['project_id'],
            'type' => $validated['type'],
            'location' => $validated['location'] ?? null,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'employee_ids' => array_map('intval', $validated['employee_ids']),
            'start' => $this->moment($validated['start_time']),
        ]);

        return (new AppointmentResource($appointment->load(self::RELATIONS)))->response()->setStatusCode(201);
    }

    public function confirmOption(Request $request, Appointment $appointment): AppointmentResource
    {
        Gate::authorize('respond', $appointment);

        $validated = $request->validate(['option_id' => ['required', 'integer']]);

        return new AppointmentResource(
            $this->appointments->confirmOption($appointment, (int) $validated['option_id'])->load(self::RELATIONS),
        );
    }

    public function alternative(Request $request, Appointment $appointment): AppointmentResource
    {
        Gate::authorize('respond', $appointment);

        $validated = $request->validate(['start_time' => ['required', 'date']]);

        return new AppointmentResource(
            $this->appointments->chooseAlternative($appointment, $this->moment($validated['start_time']))->load(self::RELATIONS),
        );
    }

    public function cancel(Appointment $appointment): AppointmentResource
    {
        Gate::authorize('respond', $appointment);

        return new AppointmentResource($this->appointments->cancelByClient($appointment)->load(self::RELATIONS));
    }

    /**
     * ISO 8601 van de app (UTC) naar de tijdzone van het platform.
     */
    private function moment(string $value): CarbonImmutable
    {
        return CarbonImmutable::parse($value)->setTimezone(config('app.timezone'));
    }
}
