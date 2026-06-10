<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\User;
use App\Models\Project;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AppointmentController extends Controller
{
    /**
     * Toon het grote admin kalender dashboard
     */
public function index()
{
    // FIX: Voeg 'attendees' toe aan de eager loading zodat JavaScript ALTIJD weet wie er gekoppeld is
    $appointments = Appointment::with(['client', 'project', 'attendees'])->get();

    $clients = User::where('is_admin', false)->orderBy('name')->get();
    $projects = Project::with('user')->get();
    $gkrEmployees = User::where('is_admin', true)->orderBy('name')->get();

    return view('admin.appointments.index', compact('appointments', 'clients', 'projects', 'gkrEmployees'));
}

    /**
     * Sla een handmatig geplande afspraak vanuit de admin op
     */
    public function store(Request $request)
    {
        // 1. Validatie van het admin-formulier
        $validated = $request->validate([
            'client_id'   => 'required|exists:users,id',
            'project_id'  => 'required|exists:projects,id',
            'title'       => 'required|string|max:255',
            'type'        => 'required|in:telefoon,online,fysiek',
            'date'        => 'required|date_format:Y-m-d',
            'time_slot'   => 'required|string',
            'description' => 'nullable|string|max:500',
            'employees'   => 'required|array|min:1',
            'employees.*' => 'exists:users,id',
        ]);

        // 2. Tijdslot splitsen ("09:00 - 10:00") naar echte start- en eindtijden
        $slots = explode(' - ', $validated['time_slot']);
        $start_time = Carbon::createFromFormat('Y-m-d H:i', $validated['date'] . ' ' . $slots[0]);
        $end_time   = Carbon::createFromFormat('Y-m-d H:i', $validated['date'] . ' ' . $slots[1]);

        // 3. Afspraak opslaan (Admin afspraken zijn direct 'Bevestigd')
        $appointment = Appointment::create([
            'user_id'     => $validated['client_id'], // Gekoppelde klant
            'project_id'  => $validated['project_id'],
            'title'       => $validated['title'],
            'type'        => $validated['type'],
            'start_time'  => $start_time,
            'end_time'    => $end_time,
            'status'      => 'Bevestigd', 
            'description' => $validated['description'],
        ]);

        // 4. Koppel de geselecteerde GKR medewerkers via de pivot-tabel
        if ($request->has('employees')) {
            $appointment->employees()->sync($validated['employees']);
        }

        return redirect()->route('admin.appointments.index')->with('success', 'De afspraak is succesvol ingepland en bevestigd!');
    }

    /**
     * Admin keurt een afspraak goed
     */
    public function approve(Appointment $appointment)
    {
        $appointment->update(['status' => 'Bevestigd']);

        return redirect()->back()->with('success', 'Afspraak is succesvol bevestigd!');
    }

    /**
     * Admin wijst een afspraak af / verwijdert deze
     */
    public function reject(Appointment $appointment)
    {
        $appointment->update(['status' => 'Geannuleerd']);

        return redirect()->back()->with('success', 'Afspraak status is bijgewerkt naar geannuleerd.');
    }

    /**
     * Controleer live de beschikbaarheid van een medewerker (Iteratie 1 Mock Data)
     */
 public function checkAvailability(Request $request)
{
    // 1. Valideer de binnenkomende data van de klant
    $request->validate([
        'employee_id' => 'required|exists:users,id',
        'date'        => 'required|date_format:Y-m-d',
        'time_slot'   => 'required|string',
    ]);

    // 2. Splits het gekozen urenslot op (bijv. "09:00 - 10:00")
    $slots = explode(' - ', $request->time_slot);
    if (count($slots) !== 2) {
        return response()->json(['status' => 'error', 'message' => 'Ongeldig tijdslot formaat'], 400);
    }

    $startTimeStr = $request->date . ' ' . trim($slots[0]) . ':00'; 
    $endTimeStr   = $request->date . ' ' . trim($slots[1]) . ':00'; 

    // 3. Waterdichte overlap-check met de juiste relatie: attendees
    $conflictExists = Appointment::where('status', 'Bevestigd')
        ->where(function ($query) use ($startTimeStr, $endTimeStr) {
            $query->where('start_time', '<', $endTimeStr)
                  ->where('end_time', '>', $startTimeStr);
        })
        ->whereHas('attendees', function ($query) use ($request) {
            $query->where('users.id', $request->employee_id);
        })
        ->exists();

    // 4. Geef het resultaat terug aan het JavaScript van de klant
    if ($conflictExists) {
        return response()->json(['status' => 'conflict', 'message' => 'Bezet']);
    }

    return response()->json(['status' => 'available', 'message' => 'Beschikbaar']);
}
}