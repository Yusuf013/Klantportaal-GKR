<?php

namespace App\Services\Appointments;

use App\Enums\AppointmentStatus;
use App\Exceptions\Appointments\AppointmentActionNotAllowed;
use App\Exceptions\Appointments\SchedulingInProgress;
use App\Exceptions\Appointments\SlotUnavailable;
use App\Jobs\SyncAppointmentToCalendar;
use App\Mail\AppointmentConfirmed;
use App\Models\Appointment;
use App\Models\AppointmentOption;
use App\Models\Project;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Alle statusovergangen van een afspraak op één plek (ADR-006 optie C, ADR-011).
 *
 * De website-controllers en de API-controllers roepen allebei deze service aan, zodat
 * beschikbaarheid, dubbele-boekingscontrole en Outlook-sync voor beide kanalen gelijk zijn.
 * Autorisatie ("mag deze gebruiker deze afspraak zien?") zit in AppointmentPolicy; deze service
 * controleert de domeinregels ("mag deze afspraak nu deze stap zetten?").
 */
class AppointmentService
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly WorkingHours $hours,
    ) {}

    /**
     * Klant vraagt zelf een moment aan (status "In afwachting").
     *
     * @param  array{project_id: int, type: string, location?: ?string, title: string, description?: ?string, employee_ids: list<int>, start: CarbonImmutable}  $data
     */
    public function requestByClient(User $client, array $data): Appointment
    {
        $this->assertProjectBelongsTo($data['project_id'], $client);
        $location = $this->normalizeLocation($data['type'], $data['location'] ?? null);

        if ($location === Appointment::LOCATION_OP_LOCATIE) {
            throw new AppointmentActionNotAllowed('Voor een afspraak op locatie bellen we u graag even. Dien daarvoor een belverzoek in.');
        }

        $employees = $this->employees($data['employee_ids']);
        $start = $data['start'];
        $end = $start->addMinutes((int) config('appointments.client_duration_minutes'));

        $this->assertBookable($start, $end, 0);

        if ($this->availability->conflictsAt($employees, $start, $end, 0, null)['conflicts'] !== []) {
            throw SlotUnavailable::forClient();
        }

        return DB::transaction(function () use ($client, $data, $employees, $location, $start, $end) {
            $appointment = Appointment::create([
                'user_id' => $client->id,
                'organizer_user_id' => $employees->first()->id,
                'project_id' => $data['project_id'],
                'title' => $data['title'],
                'type' => $data['type'],
                'location' => $location,
                'start_time' => $start,
                'end_time' => $end,
                'description' => $data['description'] ?? null,
                'status' => AppointmentStatus::InAfwachting->value,
            ]);

            $appointment->attendees()->sync($employees->pluck('id'));

            return $appointment;
        });
    }

    /**
     * Admin stelt 1-3 momenten voor aan een klant (status "Voorstel"). De admin wordt organisator
     * en is automatisch deelnemer: de uitnodiging komt later van zijn werkmail.
     *
     * Een voorstel reserveert niets (stap 4b, regel 3); collega's zien het wel als waarschuwing.
     *
     * @param  array{client_id: int, project_id: int, type: string, location?: ?string, travel_minutes?: ?int, duration_minutes: int, title: string, description?: ?string, employee_ids?: list<int>, options: list<CarbonImmutable>}  $data
     */
    public function proposeByAdmin(User $admin, array $data): Appointment
    {
        $client = User::query()->whereKey($data['client_id'])->where('is_admin', false)->first();

        if ($client === null) {
            throw new AppointmentActionNotAllowed('Kies een klant uit de lijst.');
        }

        $this->assertProjectBelongsTo($data['project_id'], $client);
        $location = $this->normalizeLocation($data['type'], $data['location'] ?? null);
        $travel = $location === Appointment::LOCATION_OP_LOCATIE ? (int) ($data['travel_minutes'] ?? 0) : 0;
        $duration = (int) $data['duration_minutes'];
        $employees = $this->employees(array_values(array_unique([$admin->id, ...($data['employee_ids'] ?? [])])));

        $options = collect($data['options'])->sort()->values();

        foreach ($options as $start) {
            $this->assertBookable($start, $start->addMinutes($duration), $travel);
        }

        return DB::transaction(function () use ($admin, $client, $data, $employees, $location, $travel, $duration, $options) {
            $appointment = Appointment::create([
                'user_id' => $client->id,
                'organizer_user_id' => $admin->id,
                'project_id' => $data['project_id'],
                'title' => $data['title'],
                'type' => $data['type'],
                'location' => $location,
                'travel_minutes' => $travel ?: null,
                'start_time' => $options->first(),
                'end_time' => $options->first()->addMinutes($duration),
                'description' => $data['description'] ?? null,
                'status' => AppointmentStatus::Voorstel->value,
            ]);

            $appointment->attendees()->sync($employees->pluck('id'));

            foreach ($options as $start) {
                $appointment->options()->create([
                    'start_time' => $start,
                    'end_time' => $start->addMinutes($duration),
                ]);
            }

            return $appointment;
        });
    }

    /**
     * Klant kiest een van de voorgestelde momenten. Wie het eerst bevestigt, krijgt het moment.
     */
    public function confirmOption(Appointment $appointment, int $optionId): Appointment
    {
        $this->assertStatus($appointment, 'Voor deze afspraak is al een keuze gemaakt.', AppointmentStatus::Voorstel);

        $option = $appointment->options()->whereKey($optionId)->first();

        if (! $option instanceof AppointmentOption) {
            throw new AppointmentActionNotAllowed('Dit tijdslot hoort niet bij deze afspraak.');
        }

        $start = CarbonImmutable::parse($option->start_time);
        $end = CarbonImmutable::parse($option->end_time);

        if ($start->isPast()) {
            throw new AppointmentActionNotAllowed('Dit moment is al voorbij. Kies een ander moment.');
        }

        return $this->book($appointment, $start, $end, AppointmentStatus::BevestigdDoorKlant, forClient: true)['appointment'];
    }

    /**
     * "Past geen van de tijden?": klant kiest zelf een vrij moment met dezelfde duur.
     */
    public function chooseAlternative(Appointment $appointment, CarbonImmutable $start): Appointment
    {
        $this->assertStatus($appointment, 'Voor deze afspraak is al een keuze gemaakt.', AppointmentStatus::Voorstel);

        $end = $start->addMinutes($appointment->durationMinutes());
        $this->assertBookable($start, $end, $appointment->effectiveTravelMinutes());

        return $this->book($appointment, $start, $end, AppointmentStatus::AlternatiefGekozen, forClient: true)['appointment'];
    }

    /**
     * Admin bevestigt definitief. Daarna komt de afspraak in Outlook.
     *
     * @return array{appointment: Appointment, outlook_checked: bool}
     */
    public function approve(Appointment $appointment): array
    {
        $this->assertStatus(
            $appointment,
            'Deze afspraak kan niet meer bevestigd worden.',
            AppointmentStatus::InAfwachting,
            AppointmentStatus::BevestigdDoorKlant,
            AppointmentStatus::AlternatiefGekozen,
        );

        $result = $this->book(
            $appointment,
            CarbonImmutable::parse($appointment->start_time),
            CarbonImmutable::parse($appointment->end_time),
            AppointmentStatus::Bevestigd,
            forClient: false,
        );

        $this->sendConfirmationMail($result['appointment']);

        return $result;
    }

    public function reject(Appointment $appointment): Appointment
    {
        return $this->cancel($appointment);
    }

    /**
     * Klant annuleert zelf (design: "Afspraak annuleren"). Staat de afspraak al in Outlook, dan
     * krijgen alle deelnemers een annulering.
     */
    public function cancelByClient(Appointment $appointment): Appointment
    {
        if ($appointment->start_time->isPast()) {
            throw new AppointmentActionNotAllowed('Een afspraak die al begonnen of voorbij is, kunt u niet meer annuleren.');
        }

        return $this->cancel($appointment);
    }

    private function cancel(Appointment $appointment): Appointment
    {
        if ($appointment->hasStatus(AppointmentStatus::Geannuleerd)) {
            throw new AppointmentActionNotAllowed('Deze afspraak is al geannuleerd.');
        }

        return DB::transaction(function () use ($appointment) {
            $inOutlook = $appointment->outlook_event_id !== null
                || in_array($appointment->calendar_sync_status, [Appointment::SYNC_PENDING, Appointment::SYNC_SYNCED], true);

            $appointment->update([
                'status' => AppointmentStatus::Geannuleerd->value,
                'calendar_sync_status' => $inOutlook ? Appointment::SYNC_PENDING : Appointment::SYNC_NOT_REQUIRED,
            ]);

            if ($inOutlook) {
                SyncAppointmentToCalendar::dispatch($appointment->id)->afterCommit();
            }

            return $appointment;
        });
    }

    /**
     * Leg een moment definitief vast: per betrokken medewerker een lock, dan vers controleren
     * (platform + Outlook), dan pas opslaan (stap 4b, regel 2).
     *
     * @return array{appointment: Appointment, outlook_checked: bool}
     */
    private function book(Appointment $appointment, CarbonImmutable $start, CarbonImmutable $end, AppointmentStatus $newStatus, bool $forClient): array
    {
        $appointment->loadMissing(['attendees', 'organizer']);
        $employees = $appointment->internalParticipants();

        return $this->withEmployeeLocks($employees, function () use ($appointment, $employees, $start, $end, $newStatus, $forClient) {
            $check = $this->availability->conflictsAt($employees, $start, $end, $appointment->effectiveTravelMinutes(), $appointment->id);

            if ($check['conflicts'] !== []) {
                throw $forClient ? SlotUnavailable::forClient() : SlotUnavailable::forEmployee(implode(' en ', $check['conflicts']));
            }

            DB::transaction(function () use ($appointment, $start, $end, $newStatus) {
                $attributes = [
                    'start_time' => $start,
                    'end_time' => $end,
                    'status' => $newStatus->value,
                ];

                if ($newStatus === AppointmentStatus::Bevestigd) {
                    $attributes['calendar_sync_status'] = Appointment::SYNC_PENDING;
                    $attributes['calendar_sync_error'] = null;
                }

                $appointment->update($attributes);
                $appointment->options()->delete();

                if ($newStatus === AppointmentStatus::Bevestigd) {
                    SyncAppointmentToCalendar::dispatch($appointment->id)->afterCommit();
                }
            });

            return ['appointment' => $appointment->refresh(), 'outlook_checked' => ! $check['outlook_unavailable']];
        });
    }

    /**
     * Locks in vaste volgorde (op id), zodat twee verzoeken nooit op elkaar blijven wachten.
     *
     * @template T
     *
     * @param  Collection<int, User>  $employees
     * @param  callable(): T  $callback
     * @return T
     */
    private function withEmployeeLocks(Collection $employees, callable $callback): mixed
    {
        $held = [];

        try {
            foreach ($employees->pluck('id')->unique()->sort()->values() as $id) {
                $lock = Cache::lock("schedule:employee:{$id}", 30);
                $lock->block((int) config('appointments.lock_wait_seconds'));
                $held[] = $lock;
            }

            return $callback();
        } catch (LockTimeoutException) {
            throw new SchedulingInProgress;
        } finally {
            foreach (array_reverse($held) as $lock) {
                $lock->release();
            }
        }
    }

    private function assertBookable(CarbonImmutable $start, CarbonImmutable $end, int $travelMinutes): void
    {
        if ($start->isPast()) {
            throw new AppointmentActionNotAllowed('Dit moment ligt in het verleden. Kies een moment in de toekomst.');
        }

        if (! $this->hours->fits($start, $end, $travelMinutes)) {
            $open = config('appointments.day_start');
            $close = config('appointments.day_end');
            $suffix = $travelMinutes > 0 ? ', inclusief de reistijd' : '';

            throw new AppointmentActionNotAllowed("Kies een moment op een werkdag tussen {$open} en {$close}{$suffix}.");
        }

        $reason = $this->availability->closedReason($start);

        if ($reason !== null) {
            throw new AppointmentActionNotAllowed("GKR is op deze dag gesloten ({$reason}). Kies een andere dag.");
        }
    }

    private function assertStatus(Appointment $appointment, string $message, AppointmentStatus ...$allowed): void
    {
        if (! $appointment->hasStatus(...$allowed)) {
            throw new AppointmentActionNotAllowed($message);
        }
    }

    private function assertProjectBelongsTo(int $projectId, User $client): void
    {
        if (! Project::query()->whereKey($projectId)->where('user_id', $client->id)->exists()) {
            throw new AppointmentActionNotAllowed('Dit project hoort niet bij deze klant.');
        }
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, User>
     */
    private function employees(array $ids): Collection
    {
        $employees = User::query()->whereKey($ids)->where('is_admin', true)->get()
            ->sortBy(fn (User $u) => array_search($u->id, $ids, true))->values();

        if ($employees->isEmpty() || $employees->count() !== count(array_unique($ids))) {
            throw new AppointmentActionNotAllowed('Kies GKR-medewerkers uit de lijst.');
        }

        return $employees;
    }

    private function normalizeLocation(string $type, ?string $location): ?string
    {
        if ($type !== Appointment::TYPE_FYSIEK) {
            return null;
        }

        return $location ?? Appointment::LOCATION_BIJ_GKR;
    }

    /**
     * De bestaande Resend-bevestigingsmail. Standaard uit; met `only_to` alleen naar één adres
     * (Resend-sandbox). Vervangt het hardcoded adres dat hier eerder stond.
     */
    private function sendConfirmationMail(Appointment $appointment): void
    {
        if (! config('appointments.confirmation_mail.enabled')) {
            return;
        }

        $appointment->loadMissing(['client', 'attendees']);
        $onlyTo = config('appointments.confirmation_mail.only_to');

        $recipients = collect([$appointment->client])
            ->merge($appointment->attendees)
            ->filter()
            ->pluck('email')
            ->unique()
            ->when($onlyTo, fn (Collection $emails) => $emails->filter(fn ($e) => strcasecmp($e, $onlyTo) === 0));

        foreach ($recipients as $email) {
            Mail::to($email)->send(new AppointmentConfirmed($appointment));
        }
    }
}
