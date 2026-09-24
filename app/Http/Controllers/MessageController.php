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
     * Bericht opslaan en versturen (inclusief optioneel meerdere bestanden).
     */
    public function store(Request $request)
    {
        $authUser = Auth::user();

        // 1. Validatie op velden (accepteer een array van bestanden, max 5 bestanden)
        $request->validate([
            'receiver_id' => 'nullable|exists:users,id',
            'body' => 'nullable|string|max:2000',
            'files' => 'nullable|array|max:5',
            'files.*' => 'file|mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg,zip|max:10240', // Max 10MB per bestand
        ]);

        // 2. Zorg dat er ten minste tekst of bestanden aanwezig zijn
        if (!$request->filled('body') && !$request->hasFile('files')) {
            return redirect()->back()->withErrors(['body' => 'Vul een bericht in of kies ten minste één bestand.']);
        }

        // 3. Bepaal de ontvanger (originele logica)
        $receiverId = $request->receiver_id;
        if (!$authUser->isAdmin()) {
            $mainAdmin = User::where('is_admin', true)->first();
            $receiverId = $mainAdmin ? $mainAdmin->id : $request->receiver_id;
        }

        // 4. Als er bestanden zijn meegegeven, verwerk elk bestand apart
        if ($request->hasFile('files')) {
            $uploadedFiles = $request->file('files');
            
            foreach ($uploadedFiles as $index => $file) {
                if ($file->isValid()) {
                    $path = $file->store('chat-files', 'public');

                    Message::create([
                        'sender_id' => $authUser->id,
                        'receiver_id' => $receiverId,
                        // Voeg de tekst alleen toe aan het eerste bericht in de loop
                        'body' => ($index === 0) ? $request->body : null,
                        'file_path' => $path,
                        'file_name' => $file->getClientOriginalName(),
                        'file_size' => $file->getSize(),
                        'file_type' => strtolower($file->getClientOriginalExtension()),
                    ]);
                }
            }
        } else {
            // Alleen een tekstbericht zonder bestanden
            Message::create([
                'sender_id' => $authUser->id,
                'receiver_id' => $receiverId,
                'body' => $request->body,
            ]);
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