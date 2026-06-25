<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AppointmentController extends Controller
{
    /**
     * Toon het afsprakenoverzicht en bereid de boekingsmodal voor.
     */
public function index()
{
    $user = auth()->user();

    // 1. Haal alle actieve afspraken op (FIX: 'Geannuleerd' wordt nu direct uitgesloten voor de klant)
    $appointments = Appointment::where('user_id', $user->id)
        ->where('status', '!=', 'Geannuleerd') // <--- VERBERG GEANNULEERDE AFSPRAKEN DIRECT
        ->with(['project', 'attendees'])
        ->orderBy('start_time', 'asc')
        ->get();

    // 2. Haal alle projecten van deze klant op voor de project-dropdown
    $myProjects = $user->projects;

    // 3. Haal alle GKR Medewerkers (admins) op voor de medewerker-dropdowns
    $gkrEmployees = User::where('is_admin', true)->orderBy('name', 'asc')->get();

    // 4. Haal het actieve voorstel op (met de 3 keuzemomenten/options)
    $appointmentProposal = Appointment::where('user_id', $user->id)
        ->where('status', 'Voorstel')
        ->with(['project', 'attendees', 'options'])
        ->latest()
        ->first();

    // Stuur alles mee naar de view
    return view('client.appointments.index', compact('appointments', 'myProjects', 'gkrEmployees', 'appointmentProposal'));
}

    /**
     * Sla de nieuwe afspraakaanvraag op in de database.
     */
 public function store(Request $request)
{
    // 1. Schoon de employees array EERST op door lege string-waardes te verwijderen
    if ($request->has('employees')) {
        // Dit filtert alle lege keuzes ("") direct weg uit de array
        $filteredEmployees = array_filter($request->employees);
        $request->merge(['employees' => $filteredEmployees]);
    }

    // 2. Valideer de invoer (Nu met de juiste verplichte en optionele velden)
    $validated = $request->validate([
        'project_id'   => 'required|exists:projects,id',
        'type'         => 'required|in:telefoon,online,fysiek',
        'title'        => 'required|string|max:255',
        'date'         => 'required|date|after_or_equal:today',
        'time_slot'    => 'required|string', 
        'employees'    => 'required|array|min:1', // Er moet nu minimaal 1 medewerker overblijven
        'employees.*'  => 'exists:users,id',      // Elke overgebleven ID moet bestaan
        'description'  => 'nullable|string|max:500', // Nullable gemaakt conform design
    ]);

    // 3. Splits het geselecteerde tijdslot (bijv. "09:00 - 10:00")
    [$startHour, $endHour] = explode(' - ', $request->time_slot);
    
    $startTime = \Carbon\Carbon::parse($request->date . ' ' . $startHour);
    $endTime   = \Carbon\Carbon::parse($request->date . ' ' . $endHour);

    // 4. Maak de basisafspraak aan
    $appointment = Appointment::create([
        'user_id'     => auth()->id(),
        'project_id'  => $request->project_id,
        'title'       => $request->title,
        'type'        => $request->type,
        'start_time'  => $startTime,
        'end_time'    => $endTime,
        'description' => $request->description, // Slaat netjes NULL op als het leeg is
    ]);

    // 5. Koppel de overgebleven medewerker ID's aan de koppeltabel
    $appointment->attendees()->attach($request->employees);

    return redirect()->route('client.appointments.index')->with('success', 'Uw afspraakaanvraag is succesvol ingediend!');
}


public function confirmSlot(Request $request, Appointment $appointment)
{
    $request->validate([
        'option_id' => 'required|exists:appointment_options,id'
    ]);

    // Haal de geselecteerde optie op
    $selectedOption = \App\Models\AppointmentOption::where('appointment_id', $appointment->id)
        ->where('id', $request->option_id)
        ->first();

    if (!$selectedOption) {
        return response()->json(['status' => 'error', 'message' => 'Dit tijdslot is niet geldig voor deze afspraak.'], 422);
    }

    // 1. Update de hoofdafspraak met de definitieve tijden en de NIEUWE status
    $appointment->update([
        'start_time' => $selectedOption->start_time,
        'end_time'   => $selectedOption->end_time,
        'status'     => 'Bevestigd door klant' // <--- AANGEPAST: Dit triggert straks de groene banner bij de admin!
    ]);

    // 2. Verwijder de overige 3 opties uit de keuzetabel, want de keuze is gemaakt
    $appointment->options()->delete();

    // Geef een succesvol JSON antwoord terug aan de JavaScript fetch-engine
    return response()->json([
        'status' => 'success',
        'message' => 'De afspraak is succesvol definitief ingepland!'
    ]);
}


/**
     * Verwerk het alternatieve tijdstip ingediend door de klant.
     */
    public function suggestAlternative(Request $request, Appointment $appointment)
    {
        $request->validate([
            'date'      => 'required|date|after_or_equal:today',
            'time_slot' => 'required|string',
        ]);

        [$startHour, $endHour] = explode(' - ', $request->time_slot);
        
        $startTime = \Carbon\Carbon::parse($request->date . ' ' . $startHour);
        $endTime   = \Carbon\Carbon::parse($request->date . ' ' . $endHour);

        $appointment->update([
            'start_time' => $startTime,
            'end_time'   => $endTime,
            'status'     => 'Alternatief gekozen'
        ]);

        $appointment->options()->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Uw alternatieve tijdstip is succesvol ingediend bij GKR!'
        ]);
    }


    /**
 * Genereer en download een .ics bestand voor Apple/Outlook Agenda.
 */
public function downloadIcs(Appointment $appointment)
{
    // 1. Formatteer de tijden naar het iCalendar-formaat (YYYYMMDDTHMMSSZ)
    $startTime = Carbon::parse($appointment->start_time)->format('Ymd\THms\Z');
    $endTime = Carbon::parse($appointment->end_time)->format('Ymd\THms\Z');

    // 2. Maak de teksten veilig voor het bestand (escapen van komma's en enters)
    $title = $this->sanitizeIcsText($appointment->title);
    $description = $this->sanitizeIcsText($appointment->description ?? 'Gesprek via GKR Klantportaal');
    $location = $this->sanitizeIcsText($appointment->type ?? 'Online / GKR Portaal');

    // 3. Bouw de universele iCalendar-bestandsstructuur op
    $icsContent = "BEGIN:VCALENDAR\n" .
                  "VERSION:2.0\n" .
                  "PRODID:-//GKR Klantportaal//NONSGML v1.0//NL\n" .
                  "CALSCALE:GREGORIAN\n" .
                  "BEGIN:VEVENT\n" .
                  "UID:" . uniqid() . "@gkr-klantportaal.nl\n" .
                  "DTSTAMP:" . Carbon::now()->format('Ymd\THms\Z') . "\n" .
                  "DTSTART:" . $startTime . "\n" .
                  "DTEND:" . $endTime . "\n" .
                  "SUMMARY:" . $title . "\n" .
                  "DESCRIPTION:" . $description . "\n" .
                  "LOCATION:" . $location . "\n" .
                  "END:VEVENT\n" .
                  "END:VCALENDAR";

    // 4. Stuur het bestand terug als een actieve download
    return response($icsContent)
        ->header('Content-Type', 'text/calendar; charset=utf-8')
        ->header('Content-Disposition', 'attachment; filename="gkr-afspraak-' . $appointment->id . '.ics"');
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