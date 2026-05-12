<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;


class ProjectController extends Controller
{
    public function show(Project $project)
    {
        // Beveiliging: Alleen de eigenaar mag zijn eigen project zien
        if ($project->user_id !== auth()->id()) {
            abort(403, 'Dit project is niet van jou!');
        }

        return view('projects.show', compact('project'));
    }
}
