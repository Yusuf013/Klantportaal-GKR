<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class MessageController extends Controller
{
    // Alleen deze bestandstypen mogen in de browser als voorbeeld worden getoond.
    // Andere typen (Word, Excel, zip) kun je alleen downloaden.
    private const PREVIEWABLE = ['pdf', 'png', 'jpg', 'jpeg'];

    // NIEUW: bijlagen staan op de privéschijf. Die is niet via /storage/... bereikbaar,
    // dus een bestand kan alleen nog via de beveiligde routes worden geopend.
    private const DISK = 'local';

    /**
     * Mag dit bestand als voorbeeld in de browser worden getoond?
     * We kijken naar de extensie van het OPGESLAGEN bestand. Die bepaalt Laravel
     * zelf op basis van de inhoud, de naam die de gebruiker koos telt niet mee.
     */
    public static function canPreview(?string $path): bool
    {
        return $path !== null
            && in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::PREVIEWABLE, true);
    }

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

            // AANGEPAST: alleen klanten kunnen geselecteerd worden (niet een andere admin via de URL)
            $selectedClient = $selectedClientId
                ? User::where('is_admin', false)->find($selectedClientId)
                : null;

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
        $messages = Message::where('sender_id', $authUser->id)
            ->orWhere('receiver_id', $authUser->id)
            ->with(['sender', 'receiver'])
            ->orderBy('created_at', 'asc')
            ->get();

        // De hoofd-admin is de standaard ontvanger van nieuwe berichten
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

        $request->validate([
            // AANGEPAST: een admin stuurt altijd naar een KLANT. Een klant kiest zelf
            // geen ontvanger; dat is altijd GKR (zie hieronder).
            // Let op: 0 en niet false (zie de les bij de afspraken).
            'receiver_id' => $authUser->isAdmin()
                ? ['required', Rule::exists('users', 'id')->where('is_admin', 0)]
                : ['nullable'],
            'body'    => 'nullable|string|max:2000',
            'files'   => 'nullable|array|max:5',
            'files.*' => 'file|mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg,zip|max:10240', // Max 10MB per bestand
        ]);

        // Er moet ten minste tekst of een bestand zijn
        if (! $request->filled('body') && ! $request->hasFile('files')) {
            return redirect()->back()->withErrors(['body' => 'Vul een bericht in of kies ten minste één bestand.']);
        }

        // Ontvanger bepalen
        if ($authUser->isAdmin()) {
            $receiverId = $request->receiver_id;
        } else {
            // AANGEPAST: een klant stuurt altijd naar GKR. Voorheen viel de code terug op
            // receiver_id uit het formulier, en kon een klant zo een andere klant berichten.
            $mainAdmin = User::where('is_admin', true)->first();
            if (! $mainAdmin) {
                return redirect()->back()->withErrors(['body' => 'Er is op dit moment geen medewerker beschikbaar om je bericht te ontvangen.']);
            }
            $receiverId = $mainAdmin->id;
        }

        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $index => $file) {
                if ($file->isValid()) {
                    // AANGEPAST: privéschijf in plaats van 'public'
                    $path = $file->store('chat-files', self::DISK);

                    Message::create([
                        'sender_id'   => $authUser->id,
                        'receiver_id' => $receiverId,
                        // De tekst alleen bij het eerste bestand
                        'body'        => ($index === 0) ? $request->body : null,
                        'file_path'   => $path,
                        'file_name'   => $file->getClientOriginalName(),
                        'file_size'   => $file->getSize(),
                        'file_type'   => strtolower($file->getClientOriginalExtension()),
                    ]);
                }
            }
        } else {
            Message::create([
                'sender_id'   => $authUser->id,
                'receiver_id' => $receiverId,
                'body'        => $request->body,
            ]);
        }

        return redirect()->back()->with('success', 'Bericht verzonden!');
    }

    /**
     * Beveiligd bestand downloaden.
     */
    public function download(Message $message)
    {
        [$disk, $path] = $this->authorizedFile($message);

        return Storage::disk($disk)->download($path, $message->file_name);
    }

    /**
     * NIEUW: beveiligd voorbeeld in de browser (alleen pdf en afbeeldingen).
     * Vervangt de directe link naar /storage/..., die zonder inloggen werkte.
     */
    public function preview(Message $message)
    {
        [$disk, $path] = $this->authorizedFile($message);

        abort_unless(self::canPreview($path), 404, 'Voor dit bestandstype is geen voorbeeld beschikbaar.');

        return Storage::disk($disk)->response($path, null, [
            // De browser moet het opgegeven type volgen en niet zelf gaan raden
            'X-Content-Type-Options' => 'nosniff',
            // Niet bewaren in gedeelde caches
            'Cache-Control'          => 'private, no-store',
        ]);
    }

    /**
     * Gedeelde controle voor download en voorbeeld.
     * Geeft [schijf, pad] terug, of stopt met 403/404.
     */
    private function authorizedFile(Message $message): array
    {
        $user = Auth::user();

        // Alleen afzender, ontvanger of een admin
        $isParticipant = (int) $message->sender_id === (int) $user->id
            || (int) $message->receiver_id === (int) $user->id;

        abort_unless($user->isAdmin() || $isParticipant, 403, 'Geen toegang tot dit bestand.');
        abort_unless($message->file_path, 404, 'Bestand niet gevonden.');

        // Nieuwe bijlagen staan op de privéschijf, oude nog op 'public'
        foreach ([self::DISK, 'public'] as $disk) {
            if (Storage::disk($disk)->exists($message->file_path)) {
                return [$disk, $message->file_path];
            }
        }

        abort(404, 'Bestand niet gevonden.');
    }
}