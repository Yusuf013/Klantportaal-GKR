<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Comment;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function store(Request $request, Document $document)
    {
        // Veiligheid: alleen een medewerker van GKR of de klant van dit project mag reageren.
        // Voorheen kon elke ingelogde klant een reactie plaatsen bij het document van een
        // ander, door het nummer in de URL te veranderen. Ieder ander krijgt "niet gevonden".
        $user = $request->user();
        $isOwner = (int) ($document->project?->user_id ?? 0) === (int) $user->id;

        abort_unless($user->isAdmin() || $isOwner, 404);

        // Validatie van de feedback
        $request->validate([
            'content' => 'required|string|max:1000',
        ]);

        // Sla de opmerking op gekoppeld aan het document en de huidige user
        $document->comments()->create([
            // Bij welke versie hoort deze reactie? Altijd de huidige versie (leeg bij een
            // document van voor het versiebeheer). Dit komt nooit uit het formulier.
            'document_version_id' => $document->currentVersion()->value('document_versions.id'),
            'user_id' => auth()->id(),
            'content' => $request->content,
            'is_resolved' => false,
        ]);

        // Stuur de gebruiker terug met een succesmelding
        return back()->with('success', 'Je feedback is succesvol geplaatst!');
    }
}