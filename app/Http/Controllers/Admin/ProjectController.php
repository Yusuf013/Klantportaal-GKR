<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use App\Models\Document; // Netjes geïmporteerd voor je showDocument methode
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    // Toon alle lopende projecten (Admin paneel / Projecten)
    public function index()
    {
        $projects = Project::with('user')->get();
        return view('admin.projects.index', compact('projects'));
    }

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

    // Toon de projectdetailpagina met het nieuwe visuele kaarten-grid
    public function show(Project $project)
    {
        // We laden het project inclusief de documenten die er al bij horen
        $project->load('documents');

        return view('admin.projects.show', compact('project'));
    }

    // Wijziging: Hernoemd naar upload zodat deze exact matcht met je route 'admin.projects.upload'
    public function upload(Request $request, $projectId)
    {
        $request->validate([
            'document_name' => 'required|string|max:255',
            'file' => 'required|mimes:pdf,png,jpg,jpeg,webp|max:10240',
        ]);

        $path = $request->file('file')->store('documents', 'public');

        Document::create([
            'project_id' => $projectId,
            'name' => $request->document_name,
            'file_path' => $path,
            'status' => 'Verzonden',
        ]);
        
        return back()->with('success', 'Document is succesvol geüpload en zichtbaar voor de klant.');
    }

    // NIEUW: De ontbrekende methode voor de split-screen documenten- & feedbackpagina
    public function showDocument(Document $document)
    {
        // Eager load de feedback en de bijbehorende gebruikers (klant en admin)
        $document->load('comments.user');

        return view('admin.documents.show', compact('document'));
    }
}