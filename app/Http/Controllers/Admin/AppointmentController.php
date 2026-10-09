<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\AppointmentConfirmed;
use App\Models\Appointment;
use App\Models\AppointmentOption;
use App\Models\Project;
use App\Models\User;
use App\Services\AppointmentAvailability;
use App\Services\OutlookInvite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class AppointmentController extends Controller
{
    // Alleen afspraken met deze status kan een admin goedkeuren
    private const APPROVABLE_STATUSES = ['In afwachting', 'Bevestigd door klant', 'Alternatief gekozen'];

    /**
     * Toon het grote admin kalender dashboard
     */
    public function index(AppointmentAvailability $availability)
    {
        // Geannuleerde afspraken worden niet getoond
        $appointments = Appointment::where('status', '!=', 'Geannuleerd')
            ->with(['client', 'project', 'attendees'])
            ->get();

        $clients = User::where('is_admin', false)->orderBy('name')->get();
        $projects = Project::with('user')->get();
        $gkrEmployees = User::where('is_admin', true)->orderBy('name')->get();

        // Bezette tijden uit Outlook, als grijze blokken in de kalender (alleen voor GKR)
        $outlookBlocks = $availability->outlookCalendarBlocks();

        return view('admin.appointments.index', compact('appointments', 'clients', 'projects', 'gkrEmployees', 'outlookBlocks'));
    }

    /**
     * Admin stuurt een afspraakvoorstel met 1 tot 3 momenten naar de klant.
     */
    public function store(Request $request, AppointmentAvailability $availability)
    {
        // Lege keuzes uit de medewerker-dropdowns weghalen
        $request->merge([
            'employees' => array_values(array_filter((array) $request->input('employees', []))),
        ]);

        $validated = $request->validate([
            // De klant moet echt een klant zijn (geen admin)
            'client_id'                  => ['required', Rule::exists('users', 'id')->where('is_admin', 0)],
            // Het project moet van DIE klant zijn
            'project_id'                 => ['required', Rule::exists('projects', 'id')->where('user_id', $request->input('client_id'))],
            'title'                      => 'required|string|max:255',
            'type'                       => 'required|in:telefoon,online,fysiek',
            'description'                => 'nullable|string|max:500',
            'employees'                  => 'required|array|min:1',
            'employees.*'                => ['distinct', Rule::exists('users', 'id')->where('is_admin', true)],
            'proposal_dates'             => 'required|array|min:1',
            'proposal_dates.*.date'      => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today', $this->noWeekend()],
            'proposal_dates.*.time_slot' => ['nullable', Rule::in(AppointmentAvailability::SLOTS)],
        ], [
            'project_id.exists'          => 'Dit project hoort niet bij de gekozen klant.',
            'employees.*.exists'         => 'Kies alleen GKR-medewerkers.',
            'proposal_dates.*.time_slot.in' => 'Kies een van de vaste tijdslots.',
        ]);

        // Alleen rijen waarin zowel datum als tijdslot is ingevuld
        $filledSlots = collect($validated['proposal_dates'])
            ->filter(fn ($slot) => ! empty($slot['date']) && ! empty($slot['time_slot']))
            ->unique(fn ($slot) => $slot['date'] . ' ' . $slot['time_slot'])
            ->values();

        if ($filledSlots->isEmpty() || $filledSlots->count() > 3) {
            return back()->withErrors(['proposal_dates' => 'Vul 1 tot 3 momenten in (datum én tijdslot).'])->withInput();
        }

        // Zet elk moment om naar tijden en controleer of het nog kan
        $options = [];
        foreach ($filledSlots as $slot) {
            [$start, $end] = AppointmentAvailability::slotTimes($slot['date'], $slot['time_slot']);

            if (AppointmentAvailability::hasStarted($start)) {
                return back()->withErrors(['proposal_dates' => "Het moment {$slot['date']} {$slot['time_slot']} is al voorbij."])->withInput();
            }

            // Op kantoor kan niet om 09:00 (afspraak met GKR)
            if (AppointmentAvailability::startsTooEarly($validated['type'], $start)) {
                return back()->withErrors([
                    'proposal_dates' => "{$slot['date']} {$slot['time_slot']}: " . AppointmentAvailability::OFFICE_TOO_EARLY_MESSAGE,
                ])->withInput();
            }

            // Met het type erbij gelden ook de buffers (tijd vrij voor en na elke afspraak)
            $busy = $availability->busyEmployeeNames($validated['employees'], $start, $end, type: $validated['type']);
            if ($busy) {
                return back()->withErrors([
                    'proposal_dates' => "Op {$slot['date']} {$slot['time_slot']} is " . implode(' en ', $busy) . ' al bezet.',
                ])->withInput();
            }

            $options[] = [$start, $end];
        }

        DB::transaction(function () use ($validated, $options) {
            // De hoofdafspraak krijgt voorlopig het eerste moment; de klant kiest straks
            $appointment = Appointment::create([
                'project_id'  => $validated['project_id'],
                'user_id'     => $validated['client_id'],
                'title'       => $validated['title'],
                'type'        => $validated['type'],
                'description' => $validated['description'] ?? null,
                'status'      => 'Voorstel',
                'start_time'  => $options[0][0],
                'end_time'    => $options[0][1],
            ]);

            $appointment->attendees()->sync($validated['employees']);

            foreach ($options as [$start, $end]) {
                AppointmentOption::create([
                    'appointment_id' => $appointment->id,
                    'start_time'     => $start,
                    'end_time'       => $end,
                ]);
            }
        });

        return redirect()->back()->with('success', 'Afspraakvoorstel succesvol verzonden naar de klant!');
    }

    /**
     * Admin keurt een afspraak goed en verstuurt de bevestigingsmails.
     */
    public function approve(Appointment $appointment, AppointmentAvailability $availability)
    {
        // Een voorstel moet eerst door de klant gekozen worden; een geannuleerde of
        // al bevestigde afspraak kan niet (nog een keer) goedgekeurd worden
        if (! in_array($appointment->status, self::APPROVABLE_STATUSES, true)) {
            return redirect()->back()->with('error', "Een afspraak met status '{$appointment->status}' kan niet worden goedgekeurd.");
        }

        $employeeIds = $appointment->attendees()->pluck('users.id')->all();

        $result = $availability->withBookingLock(function () use ($appointment, $availability, $employeeIds) {
            // Laatste controle: is er intussen iets anders op dit moment gepland?
            // Outlook blokkeert hier NIET: de medewerker kan deze afspraak zelf al in
            // Outlook hebben gezet. Hieronder volgt wel een waarschuwing.
            $busy = $availability->busyEmployeeNames(
                $employeeIds,
                $appointment->start_time,
                $appointment->end_time,
                $appointment->id,
                withOutlook: false
            );

            if ($busy) {
                return implode(' en ', $busy);
            }

            $appointment->update(['status' => 'Bevestigd']);

            return null;
        });

        if ($result !== null) {
            return redirect()->back()->with('error', "Goedkeuren lukt niet: {$result} heeft op dit moment al een andere afspraak.");
        }

        $this->sendConfirmationMails($appointment->fresh(['client', 'attendees']));

        $message = 'Afspraak is succesvol bevestigd en de e-mailnotificaties zijn verwerkt!';

        // Staat er in Outlook al iets op dit moment? Dan melden we dat, zonder te blokkeren.
        $inOutlook = $availability->outlookBusyNames($employeeIds, $appointment->start_time, $appointment->end_time);

        if ($inOutlook) {
            $message .= ' Let op: in de Outlook-agenda van ' . implode(' en ', $inOutlook)
                . ' staat op dit moment al iets. Dat kan deze afspraak zelf zijn. Controleer het even.';
        }

        // De link voor de knop "Zet in Outlook" gaat mee, zodat de medewerker de
        // uitnodiging (met Teams-link) meteen na het goedkeuren kan versturen
        return redirect()->back()
            ->with('success', $message)
            ->with('outlook_url', OutlookInvite::composeUrl($appointment->fresh(['client', 'attendees']), auth()->user()));
    }

    /**
     * Admin wijst een afspraak af
     */
    public function reject(Appointment $appointment)
    {
        $appointment->update(['status' => 'Geannuleerd']);

        return redirect()->back()->with('success', 'Afspraak status is bijgewerkt naar geannuleerd.');
    }

    /**
     * Controleer de beschikbaarheid van één medewerker op één tijdslot.
     * Wordt door de kalender (klant én admin) per tijdslot aangeroepen.
     */
    public function checkAvailability(Request $request, AppointmentAvailability $availability)
    {
        $validated = $request->validate([
            // Alleen medewerkers van GKR; zo kan niemand de agenda van een klant aftasten
            'employee_id' => ['required', Rule::exists('users', 'id')->where('is_admin', true)],
            'date'        => ['required', 'date_format:Y-m-d'],
            'time_slot'   => ['required', Rule::in(AppointmentAvailability::SLOTS)],
            // Het soort afspraak dat in het formulier is gekozen (mag ontbreken)
            'type'        => ['nullable', 'in:telefoon,online,fysiek'],
        ]);

        [$start, $end] = AppointmentAvailability::slotTimes($validated['date'], $validated['time_slot']);

        // Zonder type rekenen we met een online afspraak (de kleinste buffer)
        $type = $validated['type'] ?? 'online';

        // Op kantoor kan niet om 09:00: dat tijdslot tonen we dan als bezet
        if (AppointmentAvailability::startsTooEarly($type, $start)) {
            return response()->json(['status' => 'conflict', 'message' => 'Bezet']);
        }

        // Telt ALLE afspraken mee die een slot bezet houden, Outlook en de buffers
        $busy = $availability->busyEmployeeNames([$validated['employee_id']], $start, $end, type: $type);

        // Bewust zonder namen of details: de klant ziet alleen Bezet of Beschikbaar
        return $busy
            ? response()->json(['status' => 'conflict', 'message' => 'Bezet'])
            : response()->json(['status' => 'available', 'message' => 'Beschikbaar']);
    }

    /**
     * Stuurt de bevestiging naar de klant en de medewerkers.
     *
     * Veilige standaard: zolang APPOINTMENT_MAIL_EVERYONE niet op true staat,
     * gaat er alleen mail naar het testadres (zoals de Resend-testomgeving vereist).
     * Zo staat er geen persoonlijk e-mailadres meer in de code.
     */
    private function sendConfirmationMails(Appointment $appointment): void
    {
        $recipients = collect([$appointment->client])
            ->merge($appointment->attendees)
            ->filter()
            ->pluck('email')
            ->filter()
            ->unique(fn ($email) => strtolower($email));

        if (! config('services.appointments.mail_everyone')) {
            $testRecipient = (string) config('services.appointments.mail_test_recipient');
            $recipients = $recipients->filter(fn ($email) => $testRecipient !== '' && strcasecmp($email, $testRecipient) === 0);
        }

        foreach ($recipients as $email) {
            try {
                Mail::to($email)->send(new AppointmentConfirmed($appointment));
            } catch (\Throwable $e) {
                // Een mislukte mail mag de goedkeuring niet ongedaan maken
                Log::error('Bevestigingsmail afspraak mislukt', [
                    'appointment_id' => $appointment->id,
                    'message'        => $e->getMessage(),
                ]);
            }
        }
    }

    private function noWeekend(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) {
            if ($value && AppointmentAvailability::isWeekend((string) $value)) {
                $fail('In het weekend kunnen geen afspraken worden gepland.');
            }
        };
    }
}