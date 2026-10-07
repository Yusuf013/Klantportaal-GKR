<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\User;
use App\Services\AppointmentAvailability;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AppointmentController extends Controller
{
    /**
     * Toon het afsprakenoverzicht en bereid de boekingsmodal voor.
     */
    public function index()
    {
        $user = auth()->user();

        // 1. Alle actieve afspraken van deze klant (geannuleerde worden niet getoond)
        $appointments = Appointment::where('user_id', $user->id)
            ->where('status', '!=', 'Geannuleerd')
            ->with(['project', 'attendees'])
            ->orderBy('start_time', 'asc')
            ->get();

        // 2. Alle projecten van deze klant voor de project-dropdown
        $myProjects = $user->projects;

        // 3. Alle GKR-medewerkers (admins) voor de medewerker-dropdowns
        $gkrEmployees = User::where('is_admin', true)->orderBy('name', 'asc')->get();

        // 4. Het actieve voorstel (met de keuzemomenten/options)
        $appointmentProposal = Appointment::where('user_id', $user->id)
            ->where('status', 'Voorstel')
            ->with(['project', 'attendees', 'options'])
            ->latest()
            ->first();

        return view('client.appointments.index', compact('appointments', 'myProjects', 'gkrEmployees', 'appointmentProposal'));
    }

    /**
     * Sla een nieuwe afspraakaanvraag van de klant op.
     */
    public function store(Request $request, AppointmentAvailability $availability)
    {
        // Lege keuzes ("") uit de medewerker-dropdowns weghalen
        $request->merge([
            'employees' => array_values(array_filter((array) $request->input('employees', []))),
        ]);

        $validated = $request->validate([
            // VEILIGHEID: het project moet van DEZE klant zijn
            'project_id'  => ['required', Rule::exists('projects', 'id')->where('user_id', $request->user()->id)],
            'type'        => 'required|in:telefoon,online,fysiek',
            'title'       => 'required|string|max:255',
            'date'        => ['bail', 'required', 'date_format:Y-m-d', 'after_or_equal:today', $this->noWeekend()],
            // Alleen de vaste tijdslots uit de kalender zijn toegestaan
            'time_slot'   => ['required', Rule::in(AppointmentAvailability::SLOTS)],
            'employees'   => 'required|array|min:1',
            // VEILIGHEID: alleen GKR-medewerkers (admins), geen andere klanten
            'employees.*' => ['distinct', Rule::exists('users', 'id')->where('is_admin', true)],
            'description' => 'nullable|string|max:500',
        ], $this->messages());

        [$startTime, $endTime] = AppointmentAvailability::slotTimes($validated['date'], $validated['time_slot']);

        if (AppointmentAvailability::hasStarted($startTime)) {
            return back()->withErrors(['time_slot' => 'Dit tijdstip is al voorbij. Kies een later moment.'])->withInput();
        }

        // Controle + opslaan gebeuren samen, terwijl niemand anders tegelijk boekt
        return $availability->withBookingLock(function () use ($request, $validated, $startTime, $endTime, $availability) {
            // De server controleert opnieuw: de check in de browser is te omzeilen
            $busy = $availability->busyEmployeeNames($validated['employees'], $startTime, $endTime);

            if ($busy) {
                return back()->withErrors([
                    'time_slot' => 'Dit tijdstip is inmiddels bezet. Kies een ander moment.',
                ])->withInput();
            }

            DB::transaction(function () use ($request, $validated, $startTime, $endTime) {
                $appointment = Appointment::create([
                    'user_id'     => $request->user()->id,
                    'project_id'  => $validated['project_id'],
                    'title'       => $validated['title'],
                    'type'        => $validated['type'],
                    'start_time'  => $startTime,
                    'end_time'    => $endTime,
                    'description' => $validated['description'] ?? null,
                    'status'      => 'In afwachting',
                ]);

                $appointment->attendees()->attach($validated['employees']);
            });

            return redirect()->route('client.appointments.index')->with('success', 'Uw afspraakaanvraag is succesvol ingediend!');
        });
    }

    /**
     * De klant kiest één van de voorgestelde momenten van GKR.
     */
    public function confirmSlot(Request $request, Appointment $appointment, AppointmentAvailability $availability)
    {
        // VEILIGHEID: alleen de eigen afspraak, en alleen zolang het nog een voorstel is
        if ($response = $this->guardOwnProposal($request, $appointment)) {
            return $response;
        }

        $request->validate(['option_id' => 'required|integer']);

        // Zoek de optie ALLEEN binnen deze afspraak
        $option = $appointment->options()->whereKey($request->input('option_id'))->first();

        if (! $option) {
            return response()->json(['status' => 'error', 'message' => 'Dit tijdslot is niet geldig voor deze afspraak.'], 422);
        }

        if (AppointmentAvailability::hasStarted($option->start_time)) {
            return response()->json(['status' => 'error', 'message' => 'Dit moment is al voorbij. Stel een ander moment voor.'], 422);
        }

        return $availability->withBookingLock(function () use ($appointment, $option, $availability) {
            // Een voorstel kan dagen oud zijn: is het moment intussen bezet geraakt?
            $busy = $availability->busyEmployeeNames(
                $appointment->attendees()->pluck('users.id')->all(),
                $option->start_time,
                $option->end_time,
                $appointment->id
            );

            if ($busy) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Dit moment is inmiddels bezet. Kies een ander moment of stel zelf een moment voor.',
                ], 422);
            }

            DB::transaction(function () use ($appointment, $option) {
                $appointment->update([
                    'start_time' => $option->start_time,
                    'end_time'   => $option->end_time,
                    'status'     => 'Bevestigd door klant',
                ]);

                // De keuze is gemaakt, de andere opties zijn niet meer nodig
                $appointment->options()->delete();
            });

            return response()->json(['status' => 'success', 'message' => 'De afspraak is succesvol definitief ingepland!']);
        });
    }

    /**
     * De klant stelt zelf een ander moment voor.
     */
    public function suggestAlternative(Request $request, Appointment $appointment, AppointmentAvailability $availability)
    {
        // VEILIGHEID: alleen de eigen afspraak, en alleen zolang het nog een voorstel is
        if ($response = $this->guardOwnProposal($request, $appointment)) {
            return $response;
        }

        $validated = $request->validate([
            'date'      => ['bail', 'required', 'date_format:Y-m-d', 'after_or_equal:today', $this->noWeekend()],
            'time_slot' => ['required', Rule::in(AppointmentAvailability::SLOTS)],
        ], $this->messages());

        [$startTime, $endTime] = AppointmentAvailability::slotTimes($validated['date'], $validated['time_slot']);

        if (AppointmentAvailability::hasStarted($startTime)) {
            return response()->json(['status' => 'error', 'message' => 'Dit tijdstip is al voorbij. Kies een later moment.'], 422);
        }

        return $availability->withBookingLock(function () use ($appointment, $startTime, $endTime, $availability) {
            $busy = $availability->busyEmployeeNames(
                $appointment->attendees()->pluck('users.id')->all(),
                $startTime,
                $endTime,
                $appointment->id
            );

            if ($busy) {
                return response()->json(['status' => 'error', 'message' => 'Dit tijdstip is inmiddels bezet. Kies een ander moment.'], 422);
            }

            DB::transaction(function () use ($appointment, $startTime, $endTime) {
                $appointment->update([
                    'start_time' => $startTime,
                    'end_time'   => $endTime,
                    'status'     => 'Alternatief gekozen',
                ]);

                $appointment->options()->delete();
            });

            return response()->json(['status' => 'success', 'message' => 'Uw alternatieve tijdstip is succesvol ingediend bij GKR!']);
        });
    }

    /**
     * Agendabestand (.ics) voor Outlook, Apple en Google Agenda.
     *
     * VEILIGHEID: de route heeft 'signed:relative'-middleware. De link in de
     * bevestigingsmail bevat een handtekening; een zelf verzonnen of aangepaste
     * link (bijv. ander nummer) wordt geweigerd. Inloggen is niet nodig, zodat
     * de link ook vanuit de mail werkt.
     */
    public function downloadIcs(Appointment $appointment)
    {
        // Tijden in UTC (de "Z" erachter betekent UTC). Voorheen stond er 'Hms',
        // waarbij 'm' de MAAND is in plaats van de minuten, en werd Nederlandse
        // tijd onterecht als UTC aangemerkt (1 à 2 uur verschoven in Outlook).
        $start = AppointmentAvailability::toUtc($appointment->start_time)->format('Ymd\THis\Z');
        $end = AppointmentAvailability::toUtc($appointment->end_time)->format('Ymd\THis\Z');

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//GKR Klantportaal//NL',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            // Vaste code per afspraak: twee keer downloaden geeft geen dubbele afspraak in de agenda
            'UID:afspraak-' . $appointment->id . '@gkr-klantportaal',
            'DTSTAMP:' . now()->utc()->format('Ymd\THis\Z'),
            'DTSTART:' . $start,
            'DTEND:' . $end,
            'SUMMARY:' . $this->sanitizeIcsText($appointment->title),
            'DESCRIPTION:' . $this->sanitizeIcsText($appointment->description ?? 'Gesprek via GKR Klantportaal'),
            'LOCATION:' . $this->sanitizeIcsText(ucfirst($appointment->type ?? 'Online')),
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        // Het agendaformaat schrijft \r\n als regeleinde voor
        $icsContent = implode("\r\n", array_map(fn ($line) => $this->foldIcsLine($line), $lines)) . "\r\n";

        return response($icsContent)
            ->header('Content-Type', 'text/calendar; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="gkr-afspraak-' . $appointment->id . '.ics"');
    }

    /**
     * Gedeelde controle voor confirmSlot en suggestAlternative.
     * Geeft een foutantwoord terug, of null als alles in orde is.
     */
    private function guardOwnProposal(Request $request, Appointment $appointment)
    {
        // Andermans afspraak? Dan doen we alsof hij niet bestaat (404), zodat
        // niemand kan uitproberen welke afspraaknummers er zijn
        abort_if((int) $appointment->user_id !== (int) $request->user()->id, 404);

        if ($appointment->status !== 'Voorstel') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Dit voorstel is al verwerkt. Ververs de pagina.',
            ], 422);
        }

        return null;
    }

    private function noWeekend(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) {
            if (AppointmentAvailability::isWeekend((string) $value)) {
                $fail('In het weekend kunnen geen afspraken worden gepland.');
            }
        };
    }

    private function messages(): array
    {
        return [
            'project_id.exists'    => 'Kies een van uw eigen projecten.',
            'employees.*.exists'   => 'Kies alleen medewerkers van GKR.',
            'employees.*.distinct' => 'U heeft dezelfde medewerker twee keer gekozen.',
            'time_slot.in'         => 'Kies een van de beschikbare tijdslots.',
            'date.after_or_equal'  => 'Kies een datum vanaf vandaag.',
        ];
    }

    /**
     * Speciale tekens veilig maken volgens de iCalendar-regels.
     */
    private function sanitizeIcsText(?string $text): string
    {
        $text = (string) $text;
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace(';', '\;', $text);
        $text = str_replace(',', '\,', $text);
        $text = str_replace("\r", '', $text);

        return str_replace("\n", '\\n', $text);
    }

    /**
     * Regels langer dan 75 tekens moeten worden "gevouwen" (regel-einde + spatie).
     * mb_strcut zorgt dat we nooit midden in een letter als é knippen.
     */
    private function foldIcsLine(string $line): string
    {
        $parts = [];

        while (strlen($line) > 75) {
            $chunk = mb_strcut($line, 0, 75, 'UTF-8');
            $parts[] = $chunk;
            $line = ' ' . substr($line, strlen($chunk));
        }

        $parts[] = $line;

        return implode("\r\n", $parts);
    }
}