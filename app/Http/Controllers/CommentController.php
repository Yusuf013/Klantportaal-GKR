<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Comment;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function store(Request $request, Document $document)
    {
        // Validatie van de feedback
        $request->validate([
            'content' => 'required|string|max:1000',
        ]);

        // Sla de opmerking op gekoppeld aan het document en de huidige user
        $document->comments()->create([
            'user_id' => auth()->id(),
            'content' => $request->content,
            'is_resolved' => false,
        ]);

        // Stuur de gebruiker terug met een succesmelding
        return back()->with('success', 'Je feedback is succesvol geplaatst!');
    }
}