<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;


class ProjectController extends Controller
{
 public function show(Project $project)
{
    // Beveiliging
    if ($project->user_id !== auth()->id()) {
        abort(403);
    }

    // Haal de documenten op die bij dit project horen
    $project->load('documents');

    return view('projects.show', compact('project'));
}

public function approveDocument(\App\Models\Document $document)
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
