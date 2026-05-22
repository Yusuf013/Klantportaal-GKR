<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Document;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function show(Project $project)
    {
        // Beveiliging
        if ($project->user_id !== auth()->id()) {
            abort(403);
        }

        // Haal alleen de documenten op (zonder de zware comments, want die gaan naar de nieuwe pagina!)
        $project->load('documents');

        return view('projects.show', compact('project'));
    }

    public function showDocument(Document $document)
    {
        // Veiligheid: check of de ingelogde klant wel eigenaar is van dit project
        if ($document->project->user_id !== auth()->id()) {
            abort(403);
        }

        // Eager load de feedback en de bijbehorende gebruikers
        $document->load('comments.user');

        return view('documents.show', compact('document'));
    }

    public function approveDocument(Document $document)
    {
        // Veiligheid: check of de ingelogde klant wel eigenaar is van dit project
        if ($document->project->user_id !== auth()->id()) {
            abort(403);
        }

        $document->update([
            'status' => 'Akkoord',
            'approved_at' => now(), // De 'digitale handtekening'
        ]);

        return back()->with('success', 'Document succesvol goedgekeurd!');
    }

    public function allDocuments()
{
    // Haal de projecten van de ingelogde klant op, inclusief de documenten (eager loading)
    $projects = auth()->user()->projects()->with('documents')->get();

    // Verzamel alle losse documenten uit de projecten in één overzichtelijke collectie
    $documents = $projects->flatMap(function ($project) {
        return $project->documents;
    });

    return view('documents.index', compact('documents'));
}

public function allProjects()
{
    // Haal alle projecten op van de ingelogde klant, inclusief het aantal documenten
    $projects = auth()->user()->projects()->with('documents')->get();

    // Stuur de data door naar de nieuwe view map
    return view('projects.index', compact('projects'));
}


}