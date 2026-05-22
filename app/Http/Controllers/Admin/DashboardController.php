<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;

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

}

