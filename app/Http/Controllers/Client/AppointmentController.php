<?php

namespace App\Http\Controllers\Client;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\AppointmentRules;
use App\Models\Appointment;
use App\Models\User;
use App\Services\Appointments\AppointmentService;
use App\Services\Appointments\WorkingHours;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Website: afspraken van de klant. De domeinlogica zit in AppointmentService (gedeeld met de
 * API, ADR-006/ADR-011); autorisatie in AppointmentPolicy.
 */
class AppointmentController extends Controller
{
    public function __construct(private readonly AppointmentService $appointments) {}

    /**
     * Toon het afsprakenoverzicht en bereid de boekingsmodal voor.
     */
    public function index(WorkingHours $hours)
    {
        $user = auth()->user();

        // Geannuleerde afspraken worden voor de klant direct uitgesloten
        $appointments = Appointment::where('user_id', $user->id)
            ->where('status', '!=', AppointmentStatus::Geannuleerd->value)
            ->with(['project', 'attendees'])
            ->orderBy('start_time', 'asc')
            ->get();

        $myProjects = $user->projects;
        $gkrEmployees = User::where('is_admin', true)->orderBy('name', 'asc')->get();

        // Het actieve voorstel (met de keuzemomenten)
        $appointmentProposal = Appointment::where('user_id', $user->id)
            ->where('status', AppointmentStatus::Voorstel->value)
            ->with(['project', 'attendees', 'options'])
            ->latest()
            ->first();

        $standardSlots = $hours->slotLabels();

        return view('client.appointments.index', compact('appointments', 'myProjects', 'gkrEmployees', 'appointmentProposal', 'standardSlots'));
    }

    /**
     * Sla de nieuwe afspraakaanvraag op in de database.
     */
    public function store(Request $request)
    {
        // Lege keuzes ("-- Kies naam --") wegfilteren
        $request->merge(['employees' => array_values(array_filter((array) $request->input('employees', [])))]);

        // Zelfde regels en meldingen als de app (AppointmentRules); alleen datum en tijdslot zijn
        // websitespecifiek.
        $validated = $request->validate([
            'project_id' => AppointmentRules::projectOf($request->user()->id),
            'type' => AppointmentRules::type(),
            'title' => AppointmentRules::title(),
            'description' => AppointmentRules::description(),
            'employees' => AppointmentRules::employees(1, 2),
            'employees.*' => AppointmentRules::employee(),
            'date' => 'required|date_format:Y-m-d|after_or_equal:today',
            'time_slot' => ['required', 'string', 'regex:/^\d{2}:\d{2} - \d{2}:\d{2}$/'],
        ], AppointmentRules::messages('employees'));

        [$startHour] = explode(' - ', $validated['time_slot']);

        $this->appointments->requestByClient($request->user(), [
            'project_id' => (int) $validated['project_id'],
            'type' => $validated['type'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'employee_ids' => array_map('intval', $validated['employees']),
            'start' => CarbonImmutable::parse($validated['date'].' '.$startHour, config('app.timezone')),
        ]);

        return redirect()->route('client.appointments.index')->with('success', 'Uw afspraakaanvraag is succesvol ingediend!');
    }

    public function confirmSlot(Request $request, Appointment $appointment)
    {
        Gate::authorize('respond', $appointment);

        $validated = $request->validate(['option_id' => 'required|integer']);

        $this->appointments->confirmOption($appointment, (int) $validated['option_id']);

        return response()->json([
            'status' => 'success',
            'message' => 'De afspraak is succesvol definitief ingepland!',
        ]);
    }

    /**
     * Verwerk het alternatieve tijdstip ingediend door de klant.
     */
    public function suggestAlternative(Request $request, Appointment $appointment)
    {
        Gate::authorize('respond', $appointment);

        $validated = $request->validate([
            'date' => 'required|date_format:Y-m-d|after_or_equal:today',
            'time_slot' => ['required', 'string', 'regex:/^\d{2}:\d{2} - \d{2}:\d{2}$/'],
        ]);

        [$startHour] = explode(' - ', $validated['time_slot']);

        $this->appointments->chooseAlternative(
            $appointment,
            CarbonImmutable::parse($validated['date'].' '.$startHour, config('app.timezone')),
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Uw alternatieve tijdstip is succesvol ingediend bij GKR!',
        ]);
    }

    /**
     * Genereer en download een .ics bestand voor Apple/Outlook Agenda.
     *
     * Alleen voor de klant van de afspraak en voor admins (was eerder publiek bereikbaar op
     * oplopend id). Tijden in UTC, zoals het `Z`-achtervoegsel belooft.
     */
    public function downloadIcs(Appointment $appointment)
    {
        Gate::authorize('view', $appointment);

        $format = fn ($time) => CarbonImmutable::parse($time)->utc()->format('Ymd\THis\Z');

        $title = $this->sanitizeIcsText($appointment->title);
        $description = $this->sanitizeIcsText($appointment->description ?? 'Gesprek via GKR Klantportaal');
        $location = $this->sanitizeIcsText($appointment->locationLabel());

        $icsContent = implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//GKR Klantportaal//NONSGML v1.0//NL',
            'CALSCALE:GREGORIAN',
            'BEGIN:VEVENT',
            // Vast per afspraak: opnieuw importeren werkt de bestaande afspraak bij.
            'UID:appointment-'.$appointment->id.'@gkr-klantportaal.nl',
            'DTSTAMP:'.$format(now()),
            'DTSTART:'.$format($appointment->start_time),
            'DTEND:'.$format($appointment->end_time),
            'SUMMARY:'.$title,
            'DESCRIPTION:'.$description,
            'LOCATION:'.$location,
            'END:VEVENT',
            'END:VCALENDAR',
        ]);

        return response($icsContent)
            ->header('Content-Type', 'text/calendar; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="gkr-afspraak-'.$appointment->id.'.ics"');
    }

    /**
     * Hulpfunctie om speciale tekens te filteren volgens de iCalendar-richtlijnen.
     */
    private function sanitizeIcsText($text)
    {
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace(';', '\;', $text);
        $text = str_replace(',', '\,', $text);
        $text = str_replace("\n", '\\n', $text);
        $text = str_replace("\r", '', $text);

        return $text;
    }
}
