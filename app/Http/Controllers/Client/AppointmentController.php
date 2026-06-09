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

    // 1. Haal alle afspraken van deze specifieke klant op, inclusief het project én de gekoppelde medewerkers (attendees)
    $appointments = Appointment::where('user_id', $user->id)
        ->with(['project', 'attendees']) // FIX: 'attendees' hier toegevoegd aan de array
        ->orderBy('start_time', 'asc')
        ->get();

    // 2. Haal alle projecten van deze klant op voor de project-dropdown
    $myProjects = $user->projects;

    // 3. Haal alle GKR Medewerkers (admins) op voor de medewerker-dropdowns
    $gkrEmployees = User::where('is_admin', true)->orderBy('name', 'asc')->get();

    // Stuur alles mee naar de view
    return view('client.appointments.index', compact('appointments', 'myProjects', 'gkrEmployees'));
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
}