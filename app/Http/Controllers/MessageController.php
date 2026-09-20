<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class MessageController extends Controller
{
    /**
     * Chatweergave laden.
     */
    public function index(Request $request)
    {
        $authUser = Auth::user();

        // --- ADMIN ZIJDE ---
        if ($authUser->isAdmin()) {
            $clients = User::where('is_admin', false)->orderBy('name')->get();
            $selectedClientId = $request->query('client_id') ?? $clients->first()?->id;
            $selectedClient = $selectedClientId ? User::find($selectedClientId) : null;

            $messages = collect();

            if ($selectedClient) {
                // Berichten tussen deze specifieke klant en het GKR team
                $messages = Message::where('sender_id', $selectedClient->id)
                    ->orWhere('receiver_id', $selectedClient->id)
                    ->with(['sender', 'receiver'])
                    ->orderBy('created_at', 'asc')
                    ->get();

                // Markeer berichten van de klant als gelezen
                Message::where('sender_id', $selectedClient->id)
                    ->whereNull('read_at')
                    ->update(['read_at' => now()]);
            }

            return view('chat.index', compact('clients', 'selectedClient', 'messages'));
        }

        // --- KLANT ZIJDE ---
        // Haal alle berichten op waar de klant zender of ontvanger van is
        $messages = Message::where('sender_id', $authUser->id)
            ->orWhere('receiver_id', $authUser->id)
            ->with(['sender', 'receiver'])
            ->orderBy('created_at', 'asc')
            ->get();

        // Zoek de hoofd-admin op om als standaard ontvanger-ID te gebruiken voor nieuwe berichten
        $mainAdmin = User::where('is_admin', true)->first();

        // Markeer berichten van de admin aan de klant als gelezen
        Message::where('receiver_id', $authUser->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return view('chat.index', compact('messages', 'mainAdmin'));
    }

    /**
     * Bericht opslaan en versturen (inclusief optioneel bestand).
     */
    public function store(Request $request)
    {
        $authUser = Auth::user();

        // 1. Validatie op velden
        $request->validate([
            'receiver_id' => 'nullable|exists:users,id',
            'body' => 'nullable|string|max:2000',
            'file' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg,zip|max:10240', // Max 10MB
        ]);

        // 2. Zorg dat er ten minste tekst of een bestand aanwezig is
     if (!$request->filled('body') && !$request->file('file')) {
    return redirect()->back()->withErrors(['body' => 'Vul een bericht in of kies een bestand.']);
}

        // 3. Bestand opslaan en metadata verzamelen (indien aanwezig)
        $fileData = [];
      if ($request->hasFile('file') || ($request->file('file') && $request->file('file')->isValid())) {
    $file = $request->file('file');
    
    // Sla op in storage/app/public/chat-files
    $path = $file->store('chat-files', 'public');

    $fileData = [
        'file_path' => $path,
        'file_name' => $file->getClientOriginalName(),
        'file_size' => $file->getSize(),
        'file_type' => strtolower($file->getClientOriginalExtension()),
    ];
}

        // 4. Bericht aanmaken op basis van rol (originele logica)
        if ($authUser->isAdmin()) {
            // Admin stuurt bericht naar gekozen klant
            Message::create(array_merge([
                'sender_id' => $authUser->id,
                'receiver_id' => $request->receiver_id,
                'body' => $request->body,
            ], $fileData));
        } else {
            // Klant stuurt bericht naar GKR (hoofd-admin)
            $mainAdmin = User::where('is_admin', true)->first();

            Message::create(array_merge([
                'sender_id' => $authUser->id,
                'receiver_id' => $mainAdmin ? $mainAdmin->id : $request->receiver_id,
                'body' => $request->body,
            ], $fileData));
        }

        return redirect()->back()->with('success', 'Bericht verzonden!');
    }

    /**
     * Beveiligd bestand downloaden.
     */
    public function download(Message $message)
    {
        $authUser = Auth::user();

        // Autorisatie: Alleen zender, ontvanger of een admin mag downloaden
        if (!$authUser->isAdmin() && $message->sender_id !== $authUser->id && $message->receiver_id !== $authUser->id) {
            abort(403, 'Geen toegang tot dit bestand.');
        }

        if (!$message->file_path || !Storage::disk('public')->exists($message->file_path)) {
            abort(404, 'Bestand niet gevonden.');
        }

        return Storage::disk('public')->download($message->file_path, $message->file_name);
    }
}