<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    // Toon het formulier om een project aan te maken
    public function create()
    {
        // Haal alleen gebruikers op met de rol 'client'
        $clients = User::where('role', 'client')->get();
        return view('admin.projects.create', compact('clients'));
    }

    // Sla het project op in de database
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'name' => 'required|string|max:255',
            'deadline' => 'nullable|date',
            'status' => 'required|string',
        ]);

        Project::create($validated);

        return redirect()->route('dashboard')->with('success', 'Project succesvol gekoppeld aan klant!');
    }
}