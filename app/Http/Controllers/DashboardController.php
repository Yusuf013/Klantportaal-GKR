<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Document;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use App\Services\AppointmentAvailability;
use App\Services\GoogleAdsService;
use App\Services\MetaAdsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Het dashboard van de klant: de eerste pagina na het inloggen.
 *
 * De pagina beantwoordt één vraag: "wat moet ik nu weten of doen?"
 * - wat er op de klant wacht (afspraakvoorstel, document, bericht);
 * - de eerstvolgende afspraak;
 * - de projecten met voortgang en deadline;
 * - de advertentiecijfers in het kort.
 *
 * Veiligheid: alles komt van de ingelogde klant ($request->user()). Er staat geen
 * nummer van een klant, project of account in de URL, dus er valt niets te raden.
 */
class DashboardController extends Controller
{
    // Periode van de advertentiecijfers op het dashboard (in dagen)
    private const ADS_DAYS = 28;

    // Afspraken met deze status wachten op een reactie van GKR
    private const WAITING_FOR_GKR = ['In afwachting', 'Bevestigd door klant', 'Alternatief gekozen'];

    // Documenten met deze status wachten op het akkoord van de klant
    private const DOCUMENT_WAITING = ['Verzonden', 'Wacht op ondertekening'];

    // Projecten met deze status zijn klaar
    private const PROJECT_DONE = ['Live', 'Afgerond'];

    public function index(Request $request, GoogleAdsService $googleAds, MetaAdsService $meta)
    {
        $user = $request->user();

        // Dit is de pagina van de klant. Een medewerker hoort op het dashboard van GKR.
        if ($user->isAdmin()) {
            // Een melding die nog getoond moest worden nemen we mee naar die pagina
            $request->session()->reflash();

            return redirect()->route('admin.dashboard');
        }

        $now = now(AppointmentAvailability::TIMEZONE);

        $projects = $user->projects()->get();

        // Alle afspraken van deze klant, behalve geannuleerde, op volgorde van tijd
        $appointments = Appointment::where('user_id', $user->id)
            ->where('status', '!=', 'Geannuleerd')
            ->with('attendees')
            ->orderBy('start_time')
            ->get();

        $proposals = $appointments->where('status', 'Voorstel')->values();

        // Documenten uit de eigen projecten die op akkoord wachten, nieuwste eerst
        $openDocuments = Document::whereIn('project_id', $projects->pluck('id'))
            ->whereIn('status', self::DOCUMENT_WAITING)
            ->latest()
            ->get();

        $unreadMessages = Message::unreadCountFor($user);

        return view('dashboard', [
            'greeting'        => $this->greeting($now) . ', ' . $this->firstName($user),
            'actions'         => $this->actions($proposals, $openDocuments, $unreadMessages),
            'nextAppointment' => $this->nextAppointment($appointments, $now),
            'waitingForGkr'   => $appointments
                ->filter(fn (Appointment $a) => in_array($a->status, self::WAITING_FOR_GKR, true) && $a->start_time && $a->start_time->gte($now))
                ->count(),
            'projects'        => $this->projectRows($projects, $now),
            'ads'             => $this->adsSummaries($user, $googleAds, $meta),
            'adsDays'         => self::ADS_DAYS,
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
     * Wat er op de klant wacht. Elk onderdeel is één zin met één knop.
     * Staat er niets open, dan is de lijst leeg.
     *
     * @return array<int, array{key: string, count: int, text: string, button: string, url: string}>
     */
    private function actions($proposals, $openDocuments, int $unreadMessages): array
    {
        $actions = [];

        if ($proposals->count() > 0) {
            $actions[] = [
                'key'    => 'proposals',
                'count'  => $proposals->count(),
                'text'   => $proposals->count() === 1
                    ? 'GKR heeft een afspraak voorgesteld: "' . $proposals->first()->title . '". Kies het moment dat jou past.'
                    : 'GKR heeft ' . $proposals->count() . ' afspraken voorgesteld. Kies per afspraak het moment dat jou past.',
                'button' => 'Moment kiezen',
                'url'    => route('client.appointments.index'),
            ];
        }

        if ($openDocuments->count() > 0) {
            $single = $openDocuments->count() === 1;

            $actions[] = [
                'key'    => 'documents',
                'count'  => $openDocuments->count(),
                'text'   => $single
                    ? 'Het document "' . $openDocuments->first()->name . '" wacht op je akkoord.'
                    : $openDocuments->count() . ' documenten wachten op je akkoord.',
                'button' => $single ? 'Document bekijken' : 'Documenten bekijken',
                // Bij één document meteen naar dat document, anders naar het overzicht
                'url'    => $single ? route('documents.show', $openDocuments->first()) : route('documents.index'),
            ];
        }

        if ($unreadMessages > 0) {
            $actions[] = [
                'key'    => 'messages',
                'count'  => $unreadMessages,
                'text'   => $unreadMessages === 1
                    ? 'Je hebt 1 nieuw bericht van GKR.'
                    : 'Je hebt ' . $unreadMessages . ' nieuwe berichten van GKR.',
                'button' => 'Open de chat',
                'url'    => route('chat.index'),
            ];
        }

        return $actions;
    }

    /**
     * De eerstvolgende afspraak die definitief is en nog niet voorbij.
     */
    private function nextAppointment($appointments, Carbon $now): ?array
    {
        $appointment = $appointments->first(
            fn (Appointment $a) => $a->status === 'Bevestigd' && $a->start_time && $a->end_time && $a->end_time->gte($now)
        );

        if (! $appointment) {
            return null;
        }

        $start = $appointment->start_time->copy()->locale('nl');

        return [
            'title'  => $appointment->title,
            'day'    => $start->format('j'),
            'month'  => $start->isoFormat('MMM'),
            'date'   => $start->isToday() ? 'Vandaag' : ($start->isTomorrow() ? 'Morgen' : ucfirst($start->isoFormat('dddd D MMMM'))),
            'time'   => $start->format('H:i') . ' - ' . $appointment->end_time->format('H:i') . ' uur',
            'type'   => AppointmentAvailability::typeLabel($appointment->type),
            'with'   => $appointment->attendees->pluck('name')->join(', ', ' en '),
        ];
    }

    /**
     * De projecten voor de lijst: lopende projecten eerst (vroegste deadline bovenaan), afgeronde onderaan.
     */
    private function projectRows($projects, Carbon $now): array
    {
        $today = $now->copy()->startOfDay();

        return $projects
            ->map(function (Project $project) use ($today) {
                $done = in_array($project->status, self::PROJECT_DONE, true);
                $deadline = $project->deadline ? Carbon::parse($project->deadline, AppointmentAvailability::TIMEZONE)->startOfDay() : null;
                $daysLeft = $deadline ? $this->daysBetween($today, $deadline) : null;

                return [
                    'name'     => $project->name,
                    'url'      => route('projects.show', $project),
                    'status'   => $project->status,
                    'done'     => $done,
                    // Altijd tussen 0 en 100, wat er ook in de database staat
                    'progress' => $done ? 100 : max(0, min(100, (int) $project->progress)),
                    'deadline' => match (true) {
                        $deadline === null          => 'Geen deadline',
                        $done                       => 'Deadline was ' . $deadline->format('d-m-Y'),
                        $daysLeft === 0             => 'Deadline vandaag',
                        $daysLeft === 1             => 'Deadline morgen',
                        $daysLeft > 1               => 'Deadline over ' . $daysLeft . ' dagen (' . $deadline->format('d-m-Y') . ')',
                        default                     => 'Deadline ' . $deadline->format('d-m-Y'),
                    },
                    'sort'     => [$done ? 1 : 0, $deadline ? $deadline->getTimestamp() : PHP_INT_MAX, mb_strtolower((string) $project->name)],
                ];
            })
            ->sortBy('sort')
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
     * De advertentiecijfers in het kort, per gekoppeld account.
     * Leest alleen een rapport dat al klaarligt: het dashboard wacht nooit op Google of Meta.
     * Ligt er niets klaar, dan is 'report' leeg en verwijst de pagina naar de volledige cijfers.
     */
    private function adsSummaries(User $user, GoogleAdsService $googleAds, MetaAdsService $meta): array
    {
        $sources = [
            ['Google Ads', $user->googleAdsAccount, $googleAds, 'google-ads.index'],
            ['Meta Ads', $user->metaAdAccount, $meta, 'meta-ads.index'],
        ];

        $summaries = [];

        foreach ($sources as [$label, $account, $service, $route]) {
            if (! $account) {
                continue;
            }

            try {
                $report = $service->readyInsights($account, self::ADS_DAYS);
            } catch (Throwable $e) {
                // Het dashboard mag nooit stuklopen op de advertentiecijfers
                Log::error('Advertentiecijfers voor het dashboard ophalen mislukt', ['user_id' => $user->id, 'message' => $e->getMessage()]);
                $report = null;
            }

            $summaries[] = ['label' => $label, 'url' => route($route), 'report' => $report];
        }

        return $summaries;
    }
}