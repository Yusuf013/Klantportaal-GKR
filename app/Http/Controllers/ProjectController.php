<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Document;
use App\Services\DocumentFeedbackNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectController extends Controller
{
    public function show(Project $project)
    {
        // Beveiliging: alleen je eigen project. Anders "niet gevonden" (404) en niet
        // "geen toegang" (403), zodat je niet kunt raden welke projecten bestaan.
        abort_unless($this->ownsProject($project), 404);

        // Haal alleen de documenten op (zonder de zware comments, want die gaan naar de nieuwe pagina!)
        $project->load('documents');

        return view('projects.show', compact('project'));
    }

    public function showDocument(Document $document)
    {
        // Veiligheid: alleen een document uit je eigen project (anders 404)
        abort_unless($this->ownsProject($document->project), 404);

        // Eager load de feedback (met de versie waarbij die hoort) en alle versies
        $document->load(['comments.user', 'comments.version', 'versions', 'currentVersion']);

        return view('documents.show', compact('document'));
    }

    public function approveDocument(Request $request, Document $document, DocumentFeedbackNotifier $notifier)
    {
        // Veiligheid: alleen een document uit je eigen project (anders 404)
        abort_unless($this->ownsProject($document->project), 404);

        $result = DB::transaction(function () use ($request, $document) {
            // Vergrendel het document, zodat er niet tegelijk een nieuwe versie bij kan komen
            $document = Document::whereKey($document->id)->lockForUpdate()->firstOrFail();
            $current = $document->currentVersion()->first();

            // Het formulier zegt welke versie de klant heeft bekeken. Is er intussen een
            // nieuwere versie, dan keuren we niets goed: de klant moet die eerst zien.
            if ($current && (int) $request->input('version_id') !== (int) $current->id) {
                return 'outdated';
            }

            $now = now();

            $document->update([
                'status' => 'Akkoord',
                'approved_at' => $now, // De 'digitale handtekening'
            ]);

            // Het akkoord hoort bij precies deze versie
            $current?->forceFill([
                'approved_at' => $now,
                'approved_by' => $request->user()->id,
            ])->save();

            return $current ? $current->version_number : 'approved';
        });

        if ($result === 'outdated') {
            return back()->with('error', 'Er is intussen een nieuwe versie van dit document. Bekijk die eerst voordat je akkoord geeft.');
        }

        // E-mail aan GKR: de klant heeft akkoord gegeven (met het versienummer, als dat er is)
        $notifier->approved($document, $request->user(), is_int($result) ? $result : null);

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

    /**
     * Is dit project van de ingelogde klant?
     */
    private function ownsProject(?Project $project): bool
    {
        return $project !== null && (int) $project->user_id === (int) auth()->id();
    }

}