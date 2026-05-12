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
}
