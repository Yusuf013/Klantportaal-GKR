<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\AppointmentRules;
use App\Models\Appointment;
use App\Models\Project;
use App\Models\User;
use App\Services\Appointments\AppointmentService;
use App\Services\Appointments\AvailabilityService;
use App\Services\Appointments\WorkingHours;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Website: admin-agenda. De domeinlogica zit in AppointmentService (gedeeld met de API,
 * ADR-006/ADR-011); deze controller vertaalt alleen formulieren naar service-aanroepen.
 */
class AppointmentController extends Controller
{
    public function __construct(private readonly AppointmentService $appointments) {}

    /**
     * Toon het grote admin kalender dashboard
     */
    public function index(WorkingHours $hours)
    {
        $appointments = Appointment::where('status', '!=', AppointmentStatus::Geannuleerd->value)
            ->with(['client', 'project', 'attendees'])
            ->get();

        $clients = User::where('is_admin', false)->orderBy('name')->get();
        $projects = Project::with('user')->get();
        $gkrEmployees = User::where('is_admin', true)->orderBy('name')->get();
        $standardSlots = $hours->slotLabels();
        $calendarDays = $hours->calendarDays();
        $showOnlyMine = auth()->user()->prefersOwnAppointmentsOnly();

        return view('admin.appointments.index', compact('appointments', 'clients', 'projects', 'gkrEmployees', 'standardSlots', 'calendarDays', 'showOnlyMine'));
    }

    /**
     * Sla een handmatig geplande afspraak vanuit de admin op
     */
    public function store(Request $request)
    {
        $request->merge(['employees' => array_values(array_filter((array) $request->input('employees', [])))]);

        // Zelfde regels en meldingen als de app (AppointmentRules); alleen de voorstelmomenten
        // zijn websitespecifiek.
        $validated = $request->validate([
            'client_id' => AppointmentRules::client(),
            'project_id' => AppointmentRules::projectOf((int) $request->input('client_id')),
            'title' => AppointmentRules::title(),
            'type' => AppointmentRules::type(),
            'description' => AppointmentRules::description(),
            'employees' => AppointmentRules::employees(0, 5),
            'employees.*' => AppointmentRules::employee(),
            'proposal_dates' => 'required|array|min:1|max:3',
            'proposal_dates.*.date' => 'nullable|date_format:Y-m-d',
            'proposal_dates.*.time_slot' => 'nullable|string',
        ], AppointmentRules::messages('employees', forAdmin: true));

        // Alleen de rijen die de admin daadwerkelijk heeft ingevuld
        $filled = collect($validated['proposal_dates'])
            ->filter(fn ($slot) => ! empty($slot['date']) && ! empty($slot['time_slot']))
            ->map(fn ($slot) => $this->parseSlot($slot['date'], $slot['time_slot']))
            ->values();

        if ($filled->isEmpty() || $filled->contains(null)) {
            return back()->withInput()->with('error', 'Kies minstens één geldig moment.');
        }

        $this->appointments->proposeByAdmin($request->user(), [
            'client_id' => (int) $validated['client_id'],
            'project_id' => (int) $validated['project_id'],
            'type' => $validated['type'],
            'duration_minutes' => $filled[0]['duration'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'employee_ids' => array_map('intval', $validated['employees'] ?? []),
            'options' => $filled->pluck('start')->all(),
        ]);

        return redirect()->back()->with('success', 'Afspraakvoorstel succesvol verzonden naar de klant!');
    }

    /**
     * Admin keurt een afspraak goed; daarna komt hij in Outlook.
     */
    public function approve(Appointment $appointment)
    {
        $result = $this->appointments->approve($appointment);

        $message = 'Afspraak is bevestigd en wordt in Outlook gezet.';

        if (! $result['outlook_checked']) {
            $message .= ' Let op: we konden de Outlook-agenda nu niet controleren; controleer zelf of het moment nog vrij is.';
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * Admin wijst een afspraak af / verwijdert deze
     */
    public function reject(Appointment $appointment)
    {
        $this->appointments->reject($appointment);

        return redirect()->back()->with('success', 'Afspraak status is bijgewerkt naar geannuleerd.');
    }

    /**
     * "Toon alleen mijn afspraken" onthouden per account (stap 4c).
     */
    public function updateAgendaScope(Request $request)
    {
        $validated = $request->validate(['agenda_scope' => 'required|in:mine,all']);

        $request->user()->forceFill(['agenda_scope' => $validated['agenda_scope']])->save();

        return response()->json(['status' => 'success', 'agenda_scope' => $validated['agenda_scope']]);
    }

    /**
     * Live beschikbaarheid van één medewerker voor één blok: platform én Outlook.
     * Wordt ook door klanten gebruikt; geeft daarom alleen vrij/bezet terug, nooit waarom.
     */
    public function checkAvailability(Request $request, AvailabilityService $availability)
    {
        $request->validate([
            'employee_id' => 'required|integer',
            'date' => 'required|date_format:Y-m-d',
            'time_slot' => 'required|string',
        ]);

        $employee = User::query()->whereKey($request->employee_id)->where('is_admin', true)->first();
        $slot = $this->parseSlot($request->date, $request->time_slot);

        if ($employee === null || $slot === null) {
            return response()->json(['status' => 'error', 'message' => 'Ongeldige medewerker of ongeldig tijdslot.'], 422);
        }

        $result = $availability->momentStatuses(
            collect([$employee]),
            [['start' => $slot['start'], 'end' => $slot['start']->addMinutes($slot['duration'])]],
            $slot['duration'],
        );

        $status = $result['moments'][0]['employees'][0]['status'];

        return response()->json([
            'status' => $status === AvailabilityService::STATUS_AVAILABLE ? 'available' : 'conflict',
            'message' => $status === AvailabilityService::STATUS_AVAILABLE ? 'Beschikbaar' : 'Bezet',
            'outlook_unavailable' => $result['outlook_unavailable'],
        ]);
    }

    /**
     * "2026-10-12" + "09:00 - 10:00" => start en duur.
     *
     * @return array{start: CarbonImmutable, duration: int}|null
     */
    private function parseSlot(string $date, string $timeSlot): ?array
    {
        $parts = array_map('trim', explode(' - ', $timeSlot));

        if (count($parts) !== 2 || ! preg_match('/^\d{2}:\d{2}$/', $parts[0]) || ! preg_match('/^\d{2}:\d{2}$/', $parts[1])) {
            return null;
        }

        $start = CarbonImmutable::parse($date.' '.$parts[0], config('app.timezone'));
        $end = CarbonImmutable::parse($date.' '.$parts[1], config('app.timezone'));

        return $end > $start ? ['start' => $start, 'duration' => (int) $start->diffInMinutes($end)] : null;
    }
}
