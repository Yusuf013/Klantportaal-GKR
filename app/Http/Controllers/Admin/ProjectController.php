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

public function uploadDocument(Request $request, $projectId)
{
    $request->validate([
        'document_name' => 'required|string|max:255',
        'file' => 'required|mimes:pdf,png,jpg,jpeg|max:10240',
    ]);

    $path = $request->file('file')->store('documents', 'public');

    // We slaan het resultaat op in een variabele $newDoc
    $newDoc = \App\Models\Document::create([
        'project_id' => $projectId,
        'name' => $request->document_name,
        'file_path' => $path,
        'status' => 'Verzonden',
    ]);

    // DIT GAAN WE TESTEN:
    dd($newDoc->toArray()); 
}

public function show(Project $project)
{
    // We laden het project inclusief de documenten die er al bij horen
    $project->load('documents');

    return view('admin.projects.show', compact('project'));
}
}