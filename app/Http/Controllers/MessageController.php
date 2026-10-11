<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MessageController extends Controller
{
    // Alleen deze bestandstypen mogen in de browser als voorbeeld worden getoond.
    // Andere typen (Word, Excel, zip) kun je alleen downloaden.
    private const PREVIEWABLE = ['pdf', 'png', 'jpg', 'jpeg'];

    // NIEUW: bijlagen staan op de privéschijf. Die is niet via /storage/... bereikbaar,
    // dus een bestand kan alleen nog via de beveiligde routes worden geopend.
    private const DISK = 'local';

    // Rem op versturen, per gebruiker
    private const MAX_SENDS_PER_MINUTE = 20;
    private const MAX_FILES_PER_TEN_MINUTES = 20;

    // Zoveel nieuwe berichten stuurt de server hooguit per keer naar de browser
    private const MAX_LIVE_MESSAGES = 100;

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
            // Alleen een klant die in de URL staat wordt geopend. Voorheen koos het portaal
            // zelf de eerste klant uit de lijst en zette diens berichten op gelezen, ook
            // als de medewerker dat gesprek helemaal niet had bekeken.
            // Alleen klanten kunnen geselecteerd worden (niet een andere admin via de URL).
            $clientId = $request->integer('client_id');
            $selectedClient = $clientId > 0
                ? User::where('is_admin', false)->find($clientId)
                : null;

            $messages = collect();

            if ($selectedClient) {
                $messages = $this->conversation($selectedClient->id);

                // De medewerker heeft dit gesprek nu echt open: berichten van de klant zijn gelezen
                Message::markReadBy($authUser, $selectedClient->id);
            }

            // De lijst met klanten, met per klant de teller en het laatste bericht.
            // Dit moet NA het markeren hierboven: het gesprek dat nu open staat is gelezen.
            [$clients, $overview] = $this->clientList();

            return view('chat.index', compact('clients', 'selectedClient', 'messages', 'overview'));
        }

        // --- KLANT ZIJDE ---
        // Het gesprek komt altijd van de ingelogde klant zelf, nooit uit de URL
        $messages = $this->conversation($authUser->id);

        // De klant heeft het gesprek open: antwoorden van GKR zijn gelezen
        Message::markReadBy($authUser, $authUser->id);

        return view('chat.index', compact('messages'));
    }

    /**
     * Nieuwe berichten ophalen zonder de pagina te herladen (voor de chat in de browser).
     *
     * De browser stuurt mee welk bericht hij als laatste heeft ("after"). Terug komt de
     * opmaak van alles wat nieuwer is, plus de tellers. De opmaak wordt hier op de server
     * gemaakt met dezelfde Blade-bestanden als de pagina zelf.
     *
     * Veiligheid: een klant krijgt altijd het EIGEN gesprek, wat er ook in de URL staat.
     * Alleen een medewerker kan een klant kiezen met client_id.
     */
    public function messages(Request $request)
    {
        $authUser = Auth::user();
        $after = max(0, $request->integer('after'));

        if ($authUser->isAdmin()) {
            $clientId = $request->integer('client_id');
            $selectedClient = $clientId > 0 ? User::where('is_admin', false)->find($clientId) : null;

            // Een nummer dat geen klant is: niet verklappen wat het wel is
            abort_if($clientId > 0 && ! $selectedClient, 404);

            $conversationId = $selectedClient?->id;
        } else {
            $selectedClient = null;
            $conversationId = (int) $authUser->id;
        }

        $html = '';
        $lastId = $after;
        $readUpTo = null;

        if ($conversationId) {
            // Staat het gesprek echt in beeld? Dan zijn de berichten van de andere kant gelezen.
            if ($request->boolean('seen')) {
                Message::markReadBy($authUser, $conversationId);
            }

            $new = Message::inConversation($conversationId)
                ->where('id', '>', $after)
                ->with('sender')
                ->orderBy('id')
                ->limit(self::MAX_LIVE_MESSAGES)
                ->get();

            if ($new->isNotEmpty()) {
                // De dag van het bericht ervoor, om te weten of er een datum boven moet
                $previous = $after > 0
                    ? Message::inConversation($conversationId)->where('id', '<=', $after)->orderByDesc('id')->first()
                    : null;

                $html = $this->renderMessages($new, $previous?->created_at?->toDateString());
                $lastId = (int) $new->last()->id;
            }

            // Tot en met welk eigen bericht heeft de andere kant gelezen?
            $ownSide = Message::inConversation($conversationId)->whereNotNull('read_at');
            $readUpTo = $authUser->isAdmin() ? $ownSide->fromGkr()->max('id') : $ownSide->fromClient()->max('id');
        }

        $clientsHtml = null;

        if ($authUser->isAdmin()) {
            [$clients, $overview] = $this->clientList();
            $clientsHtml = view('chat._clients', compact('clients', 'overview', 'selectedClient'))->render();
        }

        return response()->json([
            'html'         => $html,
            'last_id'      => $lastId,
            'read_up_to'   => $readUpTo ? (int) $readUpTo : null,
            'unread_total' => Message::unreadCountFor($authUser),
            // Alleen voor medewerkers: de bijgewerkte lijst met gesprekken
            'clients_html' => $clientsHtml,
        ]);
    }

    /**
     * Maakt de opmaak van een reeks berichten, met een datum boven het eerste bericht van elke dag.
     */
    private function renderMessages($messages, ?string $previousDay): string
    {
        $html = '';

        foreach ($messages as $message) {
            $day = $message->created_at->toDateString();

            $html .= view('chat._message', ['message' => $message, 'showDate' => $day !== $previousDay])->render();

            $previousDay = $day;
        }

        return $html;
    }

    /**
     * De lijst met klanten voor de medewerker, al in de goede volgorde, plus het overzicht per klant.
     * Gesprekken met ongelezen berichten bovenaan, daarna het meest recente gesprek.
     * Klanten zonder berichten staan onderaan, op naam.
     *
     * @return array{0: \Illuminate\Support\Collection, 1: array}
     */
    private function clientList(): array
    {
        $overview = $this->overview();

        $clients = User::where('is_admin', false)->orderBy('name')->get()
            ->sortBy(fn (User $client) => [
                ($overview[$client->id]['unread'] ?? 0) > 0 ? 0 : 1,
                -($overview[$client->id]['last_at'] ?? 0),
                mb_strtolower($client->name),
            ])->values();

        return [$clients, $overview];
    }

    /**
     * Overzicht voor de lijst met gesprekken van de medewerker.
     * Per klant: het aantal ongelezen berichten, het laatste bericht en wanneer dat was.
     *
     * @return array<int, array{unread: int, last_at: int, last_time: string, preview: string}>
     */
    private function overview(): array
    {
        // Eén vraag aan de database voor alle klanten samen (niet één per klant)
        $rows = Message::query()
            ->whereNotNull('client_id')
            ->selectRaw('client_id, MAX(id) as last_id')
            // Ongelezen = geschreven door de klant zelf en nog niet door GKR geopend
            ->selectRaw('SUM(CASE WHEN read_at IS NULL AND sender_id = client_id THEN 1 ELSE 0 END) as unread')
            ->groupBy('client_id')
            ->get();

        $lastMessages = Message::whereIn('id', $rows->pluck('last_id'))->get()->keyBy('id');

        $overview = [];

        foreach ($rows as $row) {
            $last = $lastMessages->get($row->last_id);

            if (! $last) {
                continue;
            }

            // Korte tekst van het laatste bericht. Alleen een bijlage? Dan de bestandsnaam.
            $text = filled($last->body)
                ? Str::limit(trim((string) $last->body), 60)
                : 'Bijlage: ' . Str::limit((string) ($last->file_name ?? 'bestand'), 45);

            // Zo zie je meteen of de klant of GKR als laatste iets schreef
            $fromClient = $last->sender_id !== null && (int) $last->sender_id === (int) $last->client_id;

            $overview[(int) $row->client_id] = [
                'unread'    => (int) $row->unread,
                'last_at'   => $last->created_at->getTimestamp(),
                'last_time' => $last->created_at->isToday() ? $last->created_at->format('H:i') : $last->created_at->format('d-m'),
                'preview'   => ($fromClient ? '' : 'GKR: ') . $text,
            ];
        }

        return $overview;
    }

    /**
     * Alle berichten uit het gesprek van één klant, oudste eerst.
     */
    private function conversation(int $clientId)
    {
        return Message::inConversation($clientId)
            ->with('sender')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * Bericht opslaan en versturen (inclusief optioneel meerdere bestanden).
     */
    public function store(Request $request)
    {
        $authUser = Auth::user();

        // Rem op versturen, per gebruiker. Zonder rem kan iemand de schijf vol zetten
        // of het gesprek volspammen.
        $sendKey = 'chat-send:' . $authUser->id;

        if (RateLimiter::tooManyAttempts($sendKey, self::MAX_SENDS_PER_MINUTE)) {
            $seconds = RateLimiter::availableIn($sendKey);

            return $this->sendFailed($request, "Je verstuurt berichten te snel achter elkaar. Probeer het over {$seconds} seconden opnieuw.", 429);
        }

        RateLimiter::hit($sendKey, 60);

        $request->validate([
            // Een admin stuurt altijd naar een KLANT. Een klant kiest zelf geen
            // ontvanger: het bericht komt altijd in het eigen gesprek met GKR.
            // Let op: 0 en niet false (zie de les bij de afspraken).
            'receiver_id' => $authUser->isAdmin()
                ? ['required', Rule::exists('users', 'id')->where('is_admin', 0)]
                : ['nullable'],
            'body'    => 'nullable|string|max:2000',
            'files'   => 'nullable|array|max:5',
            'files.*' => 'file|mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg,zip|max:10240', // Max 10MB per bestand
        ], [
            // Meldingen in gewoon Nederlands. Zonder deze regels geeft Laravel een Engelse zin.
            'receiver_id.required' => 'Kies eerst een klant om een bericht te sturen.',
            'receiver_id.exists'   => 'Deze klant bestaat niet (meer).',
            'body.max'             => 'Een bericht mag maximaal 2000 tekens lang zijn.',
            'files.max'            => 'Je kunt maximaal 5 bestanden tegelijk versturen.',
            'files.*.uploaded'     => 'Het uploaden van het bestand is niet gelukt. Is het bestand misschien te groot?',
            'files.*.file'         => 'Het uploaden van het bestand is niet gelukt. Probeer het opnieuw.',
            'files.*.mimes'        => 'Dit bestandstype kan niet worden verstuurd. Toegestaan: pdf, Word, Excel, png, jpg en zip.',
            'files.*.max'          => 'Een bestand mag maximaal 10 MB groot zijn.',
        ]);

        // Er moet ten minste tekst of een bestand zijn
        if (! $request->filled('body') && ! $request->hasFile('files')) {
            return $this->sendFailed($request, 'Vul een bericht in of kies ten minste één bestand.', 422);
        }

        $files = array_filter((array) $request->file('files', []), fn ($file) => $file && $file->isValid());

        // Aparte, ruimere rem voor bijlagen
        $filesKey = 'chat-files:' . $authUser->id;

        if ($files && RateLimiter::remaining($filesKey, self::MAX_FILES_PER_TEN_MINUTES) < count($files)) {
            $minutes = (int) ceil(RateLimiter::availableIn($filesKey) / 60);

            return $this->sendFailed($request, "Je hebt in korte tijd veel bestanden verstuurd. Probeer het over {$minutes} minuten opnieuw.", 429);
        }

        // Bij welk gesprek hoort dit bericht?
        // - Medewerker: het gesprek van de gekozen klant. Die klant is ook de ontvanger.
        // - Klant: altijd het eigen gesprek. Er is geen vaste ontvanger: GKR leest als team.
        //   (Voorheen ging het bericht naar "de eerste admin". Werd dat account
        //   verwijderd, dan verdwenen de berichten van alle klanten.)
        $clientId = $authUser->isAdmin() ? (int) $request->receiver_id : (int) $authUser->id;
        $receiverId = $authUser->isAdmin() ? $clientId : null;

        if ($files) {
            foreach (array_values($files) as $index => $file) {
                RateLimiter::hit($filesKey, 600);

                // Privéschijf: niet bereikbaar via /storage/...
                $path = $file->store('chat-files', self::DISK);

                Message::create([
                    'client_id'   => $clientId,
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
        } else {
            Message::create([
                'client_id'   => $clientId,
                'sender_id'   => $authUser->id,
                'receiver_id' => $receiverId,
                'body'        => $request->body,
            ]);
        }

        // De chat in de browser verstuurt zonder herladen en haalt het nieuwe bericht daarna zelf op
        if ($request->expectsJson()) {
            return response()->json(['ok' => true], 201);
        }

        return redirect()->back()->with('success', 'Bericht verzonden!');
    }

    /**
     * Versturen is niet gelukt. Bij de chat zonder herladen gaat de melding terug als
     * JSON, anders gaat de gebruiker terug naar de pagina met de melding erboven.
     */
    private function sendFailed(Request $request, string $message, int $status)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'errors' => ['body' => [$message]]], $status);
        }

        return redirect()->back()->withErrors(['body' => $message]);
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

        // Wie mag bij een bijlage?
        // - een medewerker van GKR;
        // - de klant van wie het gesprek is;
        // - bij een oud bericht zonder gesprek: de afzender of de ontvanger.
        $isOwnConversation = $message->client_id !== null
            && (int) $message->client_id === (int) $user->id;

        $isOldParticipant = $message->client_id === null
            && ((int) $message->sender_id === (int) $user->id || (int) $message->receiver_id === (int) $user->id);

        abort_unless($user->isAdmin() || $isOwnConversation || $isOldParticipant, 403, 'Geen toegang tot dit bestand.');
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