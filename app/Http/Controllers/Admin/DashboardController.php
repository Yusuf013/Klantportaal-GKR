<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use \App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        // Haal alle projecten op inclusief de gekoppelde klant (user)
        $projects = Project::with('user')->get();

        // We sturen de admin door naar een specifieke admin dashboard view
        return view('admin.dashboard', compact('projects'));
    }

    public function clientsIndex()
    {
        // Haal alle gebruikers op die GEEN admin zijn, inclusief hun projecten
        $clients = \App\Models\User::where('is_admin', false)->with('projects')->get();

        return view('admin.clients.index', compact('clients'));
    }

    /**
     * Toon het overzicht van alle admins en klanten (Gebruikersbeheer)
     */
    public function usersIndex()
    {
        // Haal alle admins op, gesorteerd op naam
        $admins = User::where('is_admin', true)->orderBy('name')->get();

        // Haal alle klanten op, gesorteerd op naam
        $clients = User::where('is_admin', false)->orderBy('name')->get();

        return view('admin.users.index', compact('admins', 'clients'));
    }

    /**
     * Maak een klant admin of trek adminrechten in
     */
public function toggleAdmin(\App\Models\User $user)
{
    // Voorkom dat de ingelogde admin zichzelf aanpast
    if ($user->id === auth()->id()) {
        return redirect()->back()->with('error', 'Je kunt je eigen rol niet wijzigen!');
    }

    // Wijzig de status (0 naar 1, of 1 naar 0)
    $user->is_admin = $user->is_admin ? 0 : 1;
    $user->save();

    $statusText = $user->is_admin ? 'GKR Admin' : 'Klant';
    return redirect()->back()->with('success', "{$user->name} is nu succesvol gemarkeerd als {$statusText}.");
}
}