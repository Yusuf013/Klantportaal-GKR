<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\User;
use App\Models\Project;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    /**
     * Toon het grote admin kalender dashboard
     */
    public function index()
    {
        // Haal alle afspraken op inclusief de gekoppelde klant en het project
        $appointments = Appointment::with(['client', 'project'])->get();

        // Haal alle actieve klanten en projecten op zodat de admin zelf een afspraak kan starten
        $clients = User::where('is_admin', false)->orderBy('name')->get();
        $projects = Project::with('user')->get();
        $gkrEmployees = User::where('is_admin', true)->orderBy('name')->get();

        return view('admin.appointments.index', compact('appointments', 'clients', 'projects', 'gkrEmployees'));
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
        // Je kunt hem op 'Geannuleerd' zetten, of direct deleten. Veranderen naar Geannuleerd is professioneler:
        $appointment->update(['status' => 'Geannuleerd']);

        return redirect()->back()->with('success', 'Afspraak status is bijgewerkt naar geannuleerd.');
    }
}