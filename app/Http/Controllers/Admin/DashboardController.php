<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdAccount;
use App\Models\Appointment;
use App\Models\Comment;
use App\Models\Message;
use App\Models\Project;
use App\Services\AppointmentAvailability;
use App\Services\GoogleAdsService;
use App\Services\MetaAdsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;
use \App\Models\User;

class DashboardController extends Controller
{
    // Afspraken met deze status wachten op een reactie van GKR
    private const WAITING_FOR_GKR = ['In afwachting', 'Bevestigd door klant', 'Alternatief gekozen'];

    // Projecten met deze status zijn klaar
    private const PROJECT_DONE = ['Live', 'Afgerond'];

    // Documenten met deze status zijn door de klant goedgekeurd
    private const DOCUMENT_APPROVED = ['Akkoord', 'Goedgekeurd'];

    // Zo ver vooruit kijkt het blok met deadlines (in dagen)
    private const DEADLINE_DAYS = 14;

    // Hooguit zoveel documenten krijgen een eigen regel in "wat wacht op GKR"
    private const MAX_DOCUMENT_ROWS = 3;

    /**
     * Het dashboard van GKR: de eerste pagina na het inloggen van een medewerker.
     *
     * De pagina beantwoordt één vraag: "wat vraagt vandaag aandacht?"
     * - wat er op GKR wacht (afspraakaanvragen, chats, reacties op documenten);
     * - de afspraken van vandaag;
     * - deadlines die eraan komen of al voorbij zijn;
     * - koppelingen die niet werken;
     * - alle projecten, met een filter op status.
     *
     * Deze pagina haalt niets op bij Outlook, Google of Meta: ze leest alleen wat het
     * portaal al weet. Daardoor laadt ze altijd direct.
     */
    public function index(Request $request, AppointmentAvailability $availability, GoogleAdsService $googleAds, MetaAdsService $meta)
    {
        $user = $request->user();
        $now = now(AppointmentAvailability::TIMEZONE);

        // Alle projecten inclusief de gekoppelde klant (user)
        $projects = Project::with('user')->get();

        $appointments = Appointment::where('status', '!=', 'Geannuleerd')
            ->with(['client', 'attendees'])
            ->orderBy('start_time')
            ->get();

        return view('admin.dashboard', [
            'projects'    => $projects,
            'greeting'    => $this->greeting($now) . ', ' . $this->firstName($user),
            'summary'     => $this->summary($projects),
            'actions'     => $this->actions($appointments, $now),
            'today'       => $this->todayAppointments($appointments, $now),
            'upcoming'    => $this->nextAppointmentAfterToday($appointments, $now),
            'deadlines'   => $this->deadlines($projects, $now),
            'connections' => $this->connectionProblems($availability, $googleAds, $meta),
        ]);
    }

    private function greeting(Carbon $now): string
    {
        return match (true) {
            $now->hour < 12 => 'Goedemorgen',
            $now->hour < 18 => 'Goedemiddag',
            default         => 'Goedenavond',
        };
    }

    /**
     * De voornaam: het eerste woord van de naam.
     */
    private function firstName(User $user): string
    {
        $parts = preg_split('/\s+/', trim((string) $user->name));

        return $parts[0] !== '' ? $parts[0] : 'welkom';
    }

    /**
     * Eén regel onder de begroeting: hoeveel klanten en lopende projecten er zijn.
     */
    private function summary($projects): string
    {
        $clients = User::where('is_admin', false)->count();
        $running = $projects->reject(fn (Project $p) => in_array($p->status, self::PROJECT_DONE, true))->count();

        return ($clients === 1 ? '1 klant' : $clients . ' klanten')
            . ', '
            . ($running === 1 ? '1 lopend project' : $running . ' lopende projecten');
    }

    /**
     * Wat er op GKR wacht. Elk onderdeel is één zin met één knop.
     *
     * @return array<int, array{key: string, count: int, text: string, button: string, url: string}>
     */
    private function actions($appointments, Carbon $now): array
    {
        $actions = [];

        // 1. Afspraken die op bevestiging van GKR wachten (en nog niet voorbij zijn)
        $waiting = $appointments
            ->filter(fn (Appointment $a) => in_array($a->status, self::WAITING_FOR_GKR, true) && $a->start_time && $a->start_time->gte($now))
            ->values();

        if ($waiting->count() > 0) {
            $first = $waiting->first();

            $actions[] = [
                'key'    => 'appointments',
                'count'  => $waiting->count(),
                'text'   => $waiting->count() === 1
                    ? ($first->client->name ?? 'Een klant') . ' wacht op bevestiging van de afspraak "' . $first->title . '".'
                    : $waiting->count() . ' afspraken wachten op bevestiging van GKR.',
                'button' => 'Naar de agenda',
                'url'    => route('admin.appointments.index'),
            ];
        }

        // 2. Klanten met ongelezen berichten in de chat (GKR leest als team)
        $unreadByClient = Message::query()
            ->fromClient()
            ->whereNull('read_at')
            ->selectRaw('client_id, COUNT(*) as unread')
            ->groupBy('client_id')
            ->pluck('unread', 'client_id');

        if ($unreadByClient->count() === 1) {
            $clientId = (int) $unreadByClient->keys()->first();
            $unread = (int) $unreadByClient->first();

            $actions[] = [
                'key'    => 'messages',
                'count'  => 1,
                'text'   => (User::find($clientId)->name ?? 'Een klant')
                    . ($unread === 1 ? ' heeft 1 nieuw bericht gestuurd.' : ' heeft ' . $unread . ' nieuwe berichten gestuurd.'),
                'button' => 'Open het gesprek',
                'url'    => route('chat.index', ['client_id' => $clientId]),
            ];
        } elseif ($unreadByClient->count() > 1) {
            $actions[] = [
                'key'    => 'messages',
                'count'  => $unreadByClient->count(),
                'text'   => $unreadByClient->count() . ' klanten wachten op antwoord in de chat.',
                'button' => 'Open de chat',
                'url'    => route('chat.index'),
            ];
        }

        // 3. Documenten waarop de klant als laatste heeft gereageerd
        $documents = $this->documentsAwaitingReply();

        foreach ($documents->take(self::MAX_DOCUMENT_ROWS) as $comment) {
            $actions[] = [
                'key'    => 'documents',
                'count'  => 1,
                'text'   => ($comment->user->name ?? 'De klant') . ' heeft gereageerd op het document "' . ($comment->document->name ?? 'onbekend') . '".',
                'button' => 'Reactie bekijken',
                'url'    => route('admin.documents.show', $comment->document_id),
            ];
        }

        if ($documents->count() > self::MAX_DOCUMENT_ROWS) {
            $more = $documents->count() - self::MAX_DOCUMENT_ROWS;

            $actions[] = [
                'key'    => 'documents-more',
                'count'  => $more,
                'text'   => $more === 1
                    ? 'En nog 1 document met een reactie van de klant.'
                    : 'En nog ' . $more . ' documenten met een reactie van de klant.',
                'button' => 'Naar de projecten',
                'url'    => route('admin.projects.index'),
            ];
        }

        return $actions;
    }

    /**
     * Per document de laatste reactie, als die van de klant komt en GKR dus nog niet
     * heeft geantwoord. Goedgekeurde documenten tellen niet mee: daar is niets meer te doen.
     * Nieuwste reactie eerst.
     */
    private function documentsAwaitingReply()
    {
        return Comment::query()
            ->with(['user', 'document'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            // De eerste reactie die we per document tegenkomen is de laatste (nieuwste eerst)
            ->unique('document_id')
            ->filter(fn (Comment $comment) => $comment->user
                && ! $comment->user->isAdmin()
                && $comment->document
                && ! in_array($comment->document->status, self::DOCUMENT_APPROVED, true))
            ->values();
    }

    /**
     * De definitieve afspraken van vandaag, op volgorde van tijd.
     */
    private function todayAppointments($appointments, Carbon $now): array
    {
        return $appointments
            ->filter(fn (Appointment $a) => $a->status === 'Bevestigd' && $a->start_time && $a->start_time->isSameDay($now))
            ->map(fn (Appointment $a) => $this->appointmentRow($a))
            ->values()
            ->all();
    }

    /**
     * De eerstvolgende definitieve afspraak na vandaag (voor als er vandaag niets is).
     */
    private function nextAppointmentAfterToday($appointments, Carbon $now): ?array
    {
        $endOfToday = $now->copy()->endOfDay();

        $appointment = $appointments->first(
            fn (Appointment $a) => $a->status === 'Bevestigd' && $a->start_time && $a->start_time->gt($endOfToday)
        );

        if (! $appointment) {
            return null;
        }

        $start = $appointment->start_time->copy()->locale('nl');

        return $this->appointmentRow($appointment) + [
            'date' => $start->isTomorrow() ? 'Morgen' : ucfirst($start->isoFormat('dddd D MMMM')),
        ];
    }

    private function appointmentRow(Appointment $appointment): array
    {
        return [
            'time'   => $appointment->start_time->format('H:i') . ($appointment->end_time ? ' - ' . $appointment->end_time->format('H:i') : ''),
            'title'  => $appointment->title,
            'client' => $appointment->client->name ?? 'Onbekende klant',
            'type'   => AppointmentAvailability::typeLabel($appointment->type),
            'with'   => $appointment->attendees->pluck('name')->join(', ', ' en '),
        ];
    }

    /**
     * Lopende projecten waarvan de deadline binnen 14 dagen valt of al voorbij is.
     * Het meest dringende bovenaan.
     */
    private function deadlines($projects, Carbon $now): array
    {
        $today = $now->copy()->startOfDay();

        return $projects
            ->filter(fn (Project $p) => $p->deadline && ! in_array($p->status, self::PROJECT_DONE, true))
            ->map(function (Project $project) use ($today) {
                $deadline = Carbon::parse($project->deadline, AppointmentAvailability::TIMEZONE)->startOfDay();
                $daysLeft = $this->daysBetween($today, $deadline);

                return [
                    'name'     => $project->name,
                    'client'   => $project->user->name ?? 'Onbekende klant',
                    'url'      => route('admin.projects.show', $project),
                    'date'     => $deadline->format('d-m-Y'),
                    'daysLeft' => $daysLeft,
                    'late'     => $daysLeft < 0,
                    'label'    => match (true) {
                        $daysLeft < -1  => abs($daysLeft) . ' dagen te laat',
                        $daysLeft === -1 => '1 dag te laat',
                        $daysLeft === 0 => 'Vandaag',
                        $daysLeft === 1 => 'Morgen',
                        default         => 'Over ' . $daysLeft . ' dagen',
                    },
                ];
            })
            ->filter(fn (array $row) => $row['daysLeft'] <= self::DEADLINE_DAYS)
            ->sortBy('daysLeft')
            ->values()
            ->all();
    }

    /**
     * Aantal hele dagen van de ene datum tot de andere (negatief als die al voorbij is).
     * We rekenen met de kale datums, zodat de wisseling van zomer- naar wintertijd
     * (een dag van 25 uur) de telling niet een dag laat verspringen.
     */
    private function daysBetween(Carbon $from, Carbon $to): int
    {
        $seconds = Carbon::parse($to->toDateString(), 'UTC')->getTimestamp()
            - Carbon::parse($from->toDateString(), 'UTC')->getTimestamp();

        return (int) round($seconds / 86400);
    }

    /**
     * Koppelingen die aandacht vragen. Leeg als alles werkt.
     *
     * - Outlook: een agenda die het portaal de laatste keer niet kon lezen.
     * - Google Ads en Meta: klanten van wie geen rapport klaarligt. De server zet die
     *   elke 2 uur klaar; ligt er niets, dan lukt het ophalen voor die klant niet
     *   (of er is net een nieuwe versie online gezet en de eerste ronde moet nog komen).
     *
     * @return array<int, array{key: string, text: string, button: string, url: string}>
     */
    private function connectionProblems(AppointmentAvailability $availability, GoogleAdsService $googleAds, MetaAdsService $meta): array
    {
        $problems = [];

        // Alleen lezen wat al bekend is: hier wordt geen agenda opgehaald
        foreach ($availability->outlookProblems() as $problem) {
            $problems[] = [
                'key'    => 'outlook',
                'text'   => 'De Outlook-agenda van ' . $problem['employee'] . ' kan niet gelezen worden'
                    . ($problem['since'] !== '' ? ' (sinds ' . $problem['since'] . ').' : '.'),
                'button' => 'Naar gebruikersbeheer',
                'url'    => route('admin.users.index'),
            ];
        }

        $platforms = [
            ['google-ads', 'Google Ads', AdAccount::PLATFORM_GOOGLE_ADS, $googleAds, 'admin.google-ads.index'],
            ['meta-ads', 'Meta', AdAccount::PLATFORM_META, $meta, 'admin.meta-ads.index'],
        ];

        foreach ($platforms as [$key, $label, $platform, $service, $route]) {
            // Met nepdata is er geen echte koppeling die stuk kan gaan
            if ($service->usesFakeData()) {
                continue;
            }

            $names = [];

            foreach (AdAccount::where('platform', $platform)->with('user')->orderBy('id')->get() as $account) {
                try {
                    $ready = $service->readyInsights($account, 28) !== null;
                } catch (Throwable $e) {
                    Log::error('Controle van de advertentiekoppeling mislukt', ['ad_account_id' => $account->id, 'message' => $e->getMessage()]);
                    $ready = false;
                }

                if (! $ready) {
                    $names[] = $account->user->name ?? 'onbekende klant';
                }
            }

            if ($names !== []) {
                $problems[] = [
                    'key'    => $key,
                    'text'   => $label . ': de cijfers van ' . $this->nameList($names) . ' liggen niet klaar. Blijft dit staan, dan werkt de koppeling voor '
                        . (count($names) === 1 ? 'deze klant' : 'deze klanten') . ' niet.',
                    'button' => 'Naar ' . $label,
                    'url'    => route($route),
                ];
            }
        }

        return $problems;
    }

    /**
     * "Anna", "Anna en Bram", "Anna, Bram en Cees" of "Anna, Bram en 3 anderen".
     */
    private function nameList(array $names): string
    {
        if (count($names) > 3) {
            $names = array_merge(array_slice($names, 0, 2), [(count($names) - 2) . ' anderen']);
        }

        return collect($names)->join(', ', ' en ');
    }

    public function clientsIndex()
    {
        // Haal alle gebruikers op die GEEN admin zijn, inclusief hun projecten
        $clients = \App\Models\User::where('is_admin', false)->with('projects')->get();

        return view('admin.clients.index', compact('clients'));
    }

    /**
     * Toon het overzicht van alle admins en klanten (Gebruikersbeheer)
     */
    public function usersIndex(AppointmentAvailability $availability)
    {
        // Haal alle admins op, gesorteerd op naam
        $admins = User::where('is_admin', true)->orderBy('name')->get();

        // Haal alle klanten op, gesorteerd op naam
        $clients = User::where('is_admin', false)->with(['metaAdAccount', 'googleAdsAccount'])->orderBy('name')->get();

        // Bij welke medewerkers kon de Outlook-agenda de laatste keer niet gelezen worden?
        $outlookProblems = $availability->outlookProblems();

        return view('admin.users.index', compact('admins', 'clients', 'outlookProblems'));
    }

    /**
     * Maak een klant admin of trek adminrechten in
     */
public function toggleAdmin(\App\Models\User $user)
{
    // Voorkom dat de ingelogde admin zichzelf aanpast
    if ($user->id === auth()->id()) {
        return redirect()->back()->with('error', 'Je kunt je eigen rol niet wijzigen!');
    }

    // Wijzig de status (0 naar 1, of 1 naar 0)
    $user->is_admin = $user->is_admin ? 0 : 1;

      // NIEUW: admins hebben geen eigen advertentieaccount, dus koppeling opruimen
   // Admins hebben geen eigen advertentieaccounts: koppelingen opruimen
        if ($user->is_admin) {
            $user->adAccounts()->delete();
        }

        // Andersom: een klant heeft geen agenda in het portaal, dus de Outlook-koppeling opruimen
        if (! $user->is_admin) {
            $user->outlook_ics_url = null;
        }

    $user->save();

    $statusText = $user->is_admin ? 'GKR Admin' : 'Klant';
    return redirect()->back()->with('success', "{$user->name} is nu succesvol gemarkeerd als {$statusText}.");
}
}