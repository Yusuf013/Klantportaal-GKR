<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
     * Bericht opslaan en versturen.
     */
    public function store(Request $request)
    {
        $authUser = Auth::user();

        $request->validate([
            'receiver_id' => 'nullable|exists:users,id',
            'body' => 'required|string|max:2000',
        ]);

        if ($authUser->isAdmin()) {
            // Admin stuurt bericht naar gekozen klant
            Message::create([
                'sender_id' => $authUser->id,
                'receiver_id' => $request->receiver_id,
                'body' => $request->body,
            ]);
        } else {
            // Klant stuurt bericht naar GKR (hoofd-admin)
            $mainAdmin = User::where('is_admin', true)->first();

            Message::create([
                'sender_id' => $authUser->id,
                'receiver_id' => $mainAdmin ? $mainAdmin->id : $request->receiver_id,
                'body' => $request->body,
            ]);
        }

        return redirect()->back()->with('success', 'Bericht verzonden!');
    }
}