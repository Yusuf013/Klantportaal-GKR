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
}