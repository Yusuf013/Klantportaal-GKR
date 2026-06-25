<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\User;
use App\Models\Project;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\AppointmentOption;
use App\Mail\AppointmentConfirmed;
use Illuminate\Support\Facades\Mail;


class AppointmentController extends Controller
{
    /**
     * Toon het grote admin kalender dashboard
     */
/**
     * Toon het grote admin kalender dashboard
     */
    public function index()
    {
        // FIX: Filter geannuleerde eruit én neem nu ook 'Alternatief gekozen' mee naast de rest!
        $appointments = Appointment::where('status', '!=', 'Geannuleerd')
            ->with(['client', 'project', 'attendees'])
            ->get();

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

    // Bepaal de flow: Status is NU ALTIJD een voorstel, de klant moet zelf bevestigen!
    $status = 'Voorstel';

    // Formatteer de eerste (of enige) datum voor de hoofdtabel
    $times = explode(' - ', $filledSlots[0]['time_slot']);
    $startTime = \Carbon\Carbon::parse($filledSlots[0]['date'] . ' ' . $times[0]);
    $endTime = \Carbon\Carbon::parse($filledSlots[0]['date'] . ' ' . $times[1]);

    // Haal de klant ID op uit het request, of zoek de user_id op via het gekozen Project
    $userId = $request->client_id ?? $request->user_id;

    if (!$userId && $request->project_id) {
        $project = \App\Models\Project::find($request->project_id);
        $userId = $project ? $project->user_id : null; // Pakt automatisch de klant van het project
    }

    // Sla nu de afspraak op
    $appointment = Appointment::create([
        'project_id'  => $request->project_id,
        'user_id'     => $userId, // Dit kan nu nóóit meer null zijn!
        'title'       => $request->title,
        'type'        => $request->type,
        'description' => $request->description,
        'status'      => $status,
        'start_time'  => $startTime,
        'end_time'    => $endTime,
]);

$appointment->attendees()->sync($request->employees);

    // Sla ELK ingevuld slot op in de opties-tabel, of het er nu 1, 2 of 3 zijn
    foreach ($filledSlots as $slot) {
        $slotTimes = explode(' - ', $slot['time_slot']);
        \App\Models\AppointmentOption::create([
            'appointment_id' => $appointment->id,
            'start_time'     => \Carbon\Carbon::parse($slot['date'] . ' ' . $slotTimes[0]),
            'end_time'       => \Carbon\Carbon::parse($slot['date'] . ' ' . $slotTimes[1]),
        ]);
    }

    return redirect()->back()->with('success', 'Afspraakvoorstel succesvol verzonden naar de klant!');
}

    /**
     * Admin keurt een afspraak goed
     */

/**
 * Admin keurt een afspraak goed en triggert e-mails (Demo Veilige Versie)
 */
public function approve(Appointment $appointment)
{
    // 1. Update de status naar Bevestigd
    $appointment->update(['status' => 'Bevestigd']);

    // 2. Laad de relaties in om de e-mailadressen op te halen
    $appointment->load(['client', 'attendees']);

    // Jouw enige geverifieerde e-mailadres in de Resend Sandbox
    $allowedEmail = 'yusuftascidhl@gmail.com';

    // 3. Check de klant: stuur alleen als het jouw e-mail is
    if ($appointment->client && $appointment->client->email === $allowedEmail) {
        Mail::to($allowedEmail)->send(new AppointmentConfirmed($appointment));
    }

    // 4. Loop door alle gekoppelde medewerkers / admins
    if ($appointment->attendees && $appointment->attendees->count() > 0) {
        foreach ($appointment->attendees as $employee) {
            // Stuur de mail alléén als de medewerker jouw e-mailadres heeft
            // Hierdoor krijgt de andere admin GEEN mail en Resend geeft GEEN error!
            if ($employee->email === $allowedEmail) {
                Mail::to($allowedEmail)->send(new AppointmentConfirmed($appointment));
            }
        }
    }

    return redirect()->back()->with('success', 'Afspraak is succesvol bevestigd en de e-mailnotificaties zijn verwerkt!');
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