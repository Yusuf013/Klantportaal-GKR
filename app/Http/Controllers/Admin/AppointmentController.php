<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\User;
use App\Models\Project;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\AppointmentOption;


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
    $request->validate([
        'project_id'       => 'required|exists:projects,id',
        'client_id'        => 'required|exists:users,id',
        'title'            => 'required|string|max:255',
        'type'             => 'required|string',
        'description'      => 'nullable|string|max:500',
        'employees'        => 'required|array',
        'proposal_dates'   => 'required|array|min:1',
    ]);

    // Filter alle rijen die daadwerkelijk zijn ingevuld door de admin
    $filledSlots = collect($request->proposal_dates)->filter(function ($slot) {
        return !empty($slot['date']) && !empty($slot['time_slot']);
    })->values();

    // Bepaal de flow: 1 slot = direct Bevestigd, meer dan 1 = Voorstel
    $isProposal = $filledSlots->count() > 1;
    $status = $isProposal ? 'Voorstel' : 'Bevestigd';

    // Formatteer de eerste (of enige) datum voor de hoofdtabel
    $times = explode(' - ', $filledSlots[0]['time_slot']);
    $startTime = \Carbon\Carbon::parse($filledSlots[0]['date'] . ' ' . $times[0]);
    $endTime = \Carbon\Carbon::parse($filledSlots[0]['date'] . ' ' . $times[1]);

    // Sla de afspraak op
    $appointment = Appointment::create([
        'project_id'  => $request->project_id,
        'client_id'   => $request->client_id,
        'title'       => $request->title,
        'type'        => $request->type,
        'description' => $request->description,
        'status'      => $status,
        'start_time'  => $startTime,
        'end_time'    => $endTime,
    ]);

    $appointment->attendees()->sync($request->employees);

    // Als er meerdere momenten zijn gekozen, sla ze alle 3 op als opties voor de klant
    if ($isProposal) {
        foreach ($filledSlots as $slot) {
            $slotTimes = explode(' - ', $slot['time_slot']);
            \App\Models\AppointmentOption::create([
                'appointment_id' => $appointment->id,
                'start_time'     => \Carbon\Carbon::parse($slot['date'] . ' ' . $slotTimes[0]),
                'end_time'       => \Carbon\Carbon::parse($slot['date'] . ' ' . $slotTimes[1]),
            ]);
        }
    }

    return redirect()->back()->with('success', $isProposal ? 'Afspraakvoorstel succesvol verzonden naar de klant!' : 'Afspraak direct definitief ingepland!');
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