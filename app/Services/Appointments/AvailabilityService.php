<?php

namespace App\Services\Appointments;

use App\Contracts\CalendarProvider;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\ClosedDay;
use App\Models\User;
use App\Services\Calendar\BusyKind;
use App\Services\Calendar\BusyPeriod;
use App\Services\Calendar\CalendarRejected;
use App\Services\Calendar\CalendarTemporarilyUnavailable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Wanneer zijn GKR-medewerkers beschikbaar? (FR-08, stap 4 en 4a van ADR-011)
 *
 * Combineert drie bronnen:
 * 1. vastgelegde afspraken in het platform (inclusief reistijd);
 * 2. de Outlook-agenda's van de medewerkers (bezet, voorlopig, afwezig);
 * 3. dagen waarop heel GKR dicht is.
 *
 * Is Outlook niet bereikbaar, dan gaat het door op alleen de platformgegevens en zet het
 * `outlookUnavailable`; de schermen tonen dat in gewone taal.
 */
class AvailabilityService
{
    public const STATUS_AVAILABLE = 'available';

    public const STATUS_BUSY = 'busy';

    public const STATUS_AWAY = 'away';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_OUTSIDE_HOURS = 'outside_hours';

    // Ruimste reistijd die kan voorkomen; bepaalt hoe ver om het tijdvak heen we afspraken ophalen.
    private const MAX_TRAVEL_MARGIN_MINUTES = 180;

    public function __construct(
        private readonly CalendarProvider $calendar,
        private readonly WorkingHours $hours,
    ) {}

    /**
     * Vrije blokken per dag waarop álle gekozen medewerkers kunnen.
     *
     * @param  Collection<int, User>  $employees
     * @return array{days: list<array{date: string, closed_reason: ?string, slots: list<array{start: CarbonImmutable, end: CarbonImmutable}>}>, outlook_unavailable: bool}
     */
    public function slots(
        Collection $employees,
        CarbonImmutable $fromDate,
        CarbonImmutable $toDate,
        int $durationMinutes,
        int $travelMinutes = 0,
    ): array {
        $from = $fromDate->startOfDay();
        $to = $toDate->endOfDay();
        $busy = $this->busyIndex($employees, $from, $to, fresh: false);
        $closed = $this->closedDays($from, $to);
        $now = CarbonImmutable::now();
        $days = [];

        for ($day = $from; $day <= $to; $day = $day->addDay()) {
            $date = $day->toDateString();
            $reason = $closed[$date] ?? null;
            $slots = [];

            if ($reason === null) {
                foreach ($this->hours->slotStarts($day, $durationMinutes, $travelMinutes) as $start) {
                    $end = $start->addMinutes($durationMinutes);

                    if ($start > $now && $this->everyoneFree($employees, $busy['periods'], $start->subMinutes($travelMinutes), $end->addMinutes($travelMinutes))) {
                        $slots[] = ['start' => $start, 'end' => $end];
                    }
                }
            }

            $days[] = ['date' => $date, 'closed_reason' => $reason, 'slots' => $slots];
        }

        return ['days' => $days, 'outlook_unavailable' => $busy['outlook_unavailable']];
    }

    /**
     * Status per medewerker per voorgesteld moment, voor het admin-formulier ("Beschikbaarheid
     * Jasper: Maandag 12 mei Beschikbaar / Woensdag 14 mei Bezet (conflict)").
     *
     * Een moment zonder tijd (alleen een datum) is beschikbaar als er die dag nog een vrij blok is.
     * Openstaande voorstellen en aanvragen van collega's komen terug als `holds`: een waarschuwing,
     * geen blokkade (stap 4b, regel 1).
     *
     * @param  Collection<int, User>  $employees
     * @param  list<array{start: CarbonImmutable, end: ?CarbonImmutable}>  $moments
     * @return array{moments: list<array{start: CarbonImmutable, end: ?CarbonImmutable, closed_reason: ?string, employees: list<array{employee: User, status: string, holds: list<array{start: CarbonImmutable, end: CarbonImmutable, appointment: Appointment}>}>}>, outlook_unavailable: bool}
     */
    public function momentStatuses(
        Collection $employees,
        array $moments,
        int $durationMinutes,
        int $travelMinutes = 0,
        ?int $ignoreAppointmentId = null,
    ): array {
        if ($moments === []) {
            return ['moments' => [], 'outlook_unavailable' => false];
        }

        $from = collect($moments)->min(fn ($m) => $m['start'])->startOfDay();
        $to = collect($moments)->max(fn ($m) => $m['end'] ?? $m['start'])->endOfDay();
        $busy = $this->busyIndex($employees, $from, $to, fresh: false, ignoreAppointmentId: $ignoreAppointmentId);
        $closed = $this->closedDays($from, $to);
        $holds = $this->tentativeHolds($employees, $from, $to, $ignoreAppointmentId);
        $result = [];

        foreach ($moments as $moment) {
            $start = $moment['start'];
            $end = $moment['end'];
            $reason = $closed[$start->toDateString()] ?? null;
            $perEmployee = [];

            foreach ($employees as $employee) {
                $periods = $busy['periods'][$employee->id] ?? [];

                if ($reason !== null) {
                    $status = self::STATUS_CLOSED;
                } elseif ($end === null) {
                    $status = $this->dayStatus($employee, $periods, $start, $durationMinutes, $travelMinutes);
                } elseif (! $this->hours->fits($start, $end, $travelMinutes)) {
                    $status = self::STATUS_OUTSIDE_HOURS;
                } else {
                    $status = $this->windowStatus($periods, $start->subMinutes($travelMinutes), $end->addMinutes($travelMinutes));
                }

                $windowEnd = $end ?? $this->hours->window($start)[1];
                $windowStart = $end === null ? $this->hours->window($start)[0] : $start;

                $perEmployee[] = [
                    'employee' => $employee,
                    'status' => $status,
                    'holds' => array_values(array_filter(
                        $holds[$employee->id] ?? [],
                        fn (array $hold) => $hold['start'] < $windowEnd && $hold['end'] > $windowStart,
                    )),
                ];
            }

            $result[] = ['start' => $start, 'end' => $end, 'closed_reason' => $reason, 'employees' => $perEmployee];
        }

        return ['moments' => $result, 'outlook_unavailable' => $busy['outlook_unavailable']];
    }

    /**
     * Harde controle vlak vóór het vastleggen (stap 4b, regel 2): altijd vers uit Outlook.
     * Geeft de namen terug van medewerkers die bezet of afwezig zijn (leeg = vrij), plus of
     * Outlook meegenomen kon worden.
     *
     * @param  Collection<int, User>  $employees
     * @return array{conflicts: list<string>, outlook_unavailable: bool}
     */
    public function conflictsAt(
        Collection $employees,
        CarbonImmutable $start,
        CarbonImmutable $end,
        int $travelMinutes,
        ?int $ignoreAppointmentId,
    ): array {
        $windowStart = $start->subMinutes($travelMinutes);
        $windowEnd = $end->addMinutes($travelMinutes);
        $busy = $this->busyIndex($employees, $windowStart, $windowEnd, fresh: true, ignoreAppointmentId: $ignoreAppointmentId);
        $conflicts = [];

        foreach ($employees as $employee) {
            if ($this->windowStatus($busy['periods'][$employee->id] ?? [], $windowStart, $windowEnd) !== self::STATUS_AVAILABLE) {
                $conflicts[] = $employee->name;
            }
        }

        if ($this->closedDays($start->startOfDay(), $start->endOfDay()) !== []) {
            $conflicts = $employees->pluck('name')->all();
        }

        return ['conflicts' => $conflicts, 'outlook_unavailable' => $busy['outlook_unavailable']];
    }

    public function closedReason(CarbonImmutable $date): ?string
    {
        return $this->closedDays($date->startOfDay(), $date->endOfDay())[$date->toDateString()] ?? null;
    }

    /**
     * @param  list<BusyPeriod>  $periods
     */
    private function windowStatus(array $periods, CarbonImmutable $start, CarbonImmutable $end): string
    {
        $status = self::STATUS_AVAILABLE;

        foreach ($periods as $period) {
            if (! $period->overlaps($start, $end)) {
                continue;
            }

            if ($period->kind === BusyKind::Away) {
                return self::STATUS_AWAY;
            }

            $status = self::STATUS_BUSY;
        }

        return $status;
    }

    /**
     * @param  list<BusyPeriod>  $periods
     */
    private function dayStatus(User $employee, array $periods, CarbonImmutable $date, int $duration, int $travel): string
    {
        $starts = $this->hours->slotStarts($date, $duration, $travel);

        if ($starts === []) {
            return self::STATUS_OUTSIDE_HOURS;
        }

        foreach ($starts as $start) {
            if ($this->windowStatus($periods, $start->subMinutes($travel), $start->addMinutes($duration + $travel)) === self::STATUS_AVAILABLE) {
                return self::STATUS_AVAILABLE;
            }
        }

        [$open, $close] = $this->hours->window($date);

        return $this->windowStatus($periods, $open, $close) === self::STATUS_AWAY ? self::STATUS_AWAY : self::STATUS_BUSY;
    }

    /**
     * @param  Collection<int, User>  $employees
     * @param  array<int, list<BusyPeriod>>  $periods
     */
    private function everyoneFree(Collection $employees, array $periods, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        foreach ($employees as $employee) {
            if ($this->windowStatus($periods[$employee->id] ?? [], $start, $end) !== self::STATUS_AVAILABLE) {
                return false;
            }
        }

        return true;
    }

    /**
     * Bezette tijden per medewerker-id uit het platform én Outlook.
     *
     * @param  Collection<int, User>  $employees
     * @return array{periods: array<int, list<BusyPeriod>>, outlook_unavailable: bool}
     */
    private function busyIndex(
        Collection $employees,
        CarbonImmutable $from,
        CarbonImmutable $to,
        bool $fresh,
        ?int $ignoreAppointmentId = null,
    ): array {
        $periods = [];
        $ids = $employees->pluck('id')->all();

        if ($ids === []) {
            return ['periods' => [], 'outlook_unavailable' => false];
        }

        $appointments = $this->involving($ids)
            ->whereIn('status', AppointmentStatus::blockingValues())
            ->when($ignoreAppointmentId, fn (Builder $q) => $q->whereKeyNot($ignoreAppointmentId))
            ->where('start_time', '<', $to->addMinutes(self::MAX_TRAVEL_MARGIN_MINUTES))
            ->where('end_time', '>', $from->subMinutes(self::MAX_TRAVEL_MARGIN_MINUTES))
            ->with('attendees:id')
            ->get();

        foreach ($appointments as $appointment) {
            [$start, $end] = $appointment->occupiedWindow();
            $involved = $appointment->attendees->pluck('id')->push($appointment->organizer_user_id)->filter()->unique();

            foreach ($involved->intersect($ids) as $id) {
                $periods[$id][] = new BusyPeriod($start, $end, BusyKind::Busy);
            }
        }

        $outlookUnavailable = false;

        try {
            $byEmail = $this->outlookBusy($employees, $from, $to, $fresh);

            foreach ($employees as $employee) {
                foreach ($byEmail[strtolower($employee->email)] ?? [] as $period) {
                    $periods[$employee->id][] = $period;
                }
            }
        } catch (CalendarTemporarilyUnavailable|CalendarRejected) {
            $outlookUnavailable = true;
        }

        return ['periods' => $periods, 'outlook_unavailable' => $outlookUnavailable];
    }

    /**
     * @param  Collection<int, User>  $employees
     * @return array<string, list<BusyPeriod>>
     */
    private function outlookBusy(Collection $employees, CarbonImmutable $from, CarbonImmutable $to, bool $fresh): array
    {
        $emails = $employees->pluck('email')->map(fn ($e) => strtolower($e))->sort()->values()->all();
        $fetch = fn () => $this->calendar->busyPeriods($emails, $from, $to);

        if ($fresh) {
            return $fetch();
        }

        $key = 'availability:'.sha1(implode(',', $emails).'|'.$from->toIso8601String().'|'.$to->toIso8601String());

        return Cache::remember($key, (int) config('appointments.availability_cache_seconds'), $fetch);
    }

    /**
     * Openstaande voorstellen en aanvragen per medewerker-id (inclusief alle voorgestelde opties).
     *
     * @param  Collection<int, User>  $employees
     * @return array<int, list<array{start: CarbonImmutable, end: CarbonImmutable, appointment: Appointment}>>
     */
    private function tentativeHolds(Collection $employees, CarbonImmutable $from, CarbonImmutable $to, ?int $ignoreAppointmentId): array
    {
        $ids = $employees->pluck('id')->all();
        $holds = [];

        $appointments = $this->involving($ids)
            ->whereIn('status', AppointmentStatus::tentativeValues())
            ->when($ignoreAppointmentId, fn (Builder $q) => $q->whereKeyNot($ignoreAppointmentId))
            ->with(['attendees:id', 'options', 'client:id,name', 'organizer:id,name'])
            ->get();

        foreach ($appointments as $appointment) {
            $windows = $appointment->options->isNotEmpty()
                ? $appointment->options->map(fn ($o) => [CarbonImmutable::parse($o->start_time), CarbonImmutable::parse($o->end_time)])
                : collect([[CarbonImmutable::parse($appointment->start_time), CarbonImmutable::parse($appointment->end_time)]]);

            $involved = $appointment->attendees->pluck('id')->push($appointment->organizer_user_id)->filter()->unique();

            foreach ($windows as [$start, $end]) {
                if ($start >= $to || $end <= $from) {
                    continue;
                }

                foreach ($involved->intersect($ids) as $id) {
                    $holds[$id][] = ['start' => $start, 'end' => $end, 'appointment' => $appointment];
                }
            }
        }

        return $holds;
    }

    /**
     * @param  list<int>  $employeeIds
     */
    private function involving(array $employeeIds): Builder
    {
        return Appointment::query()->where(function (Builder $q) use ($employeeIds) {
            $q->whereIn('organizer_user_id', $employeeIds)
                ->orWhereHas('attendees', fn (Builder $a) => $a->whereIn('users.id', $employeeIds));
        });
    }

    /**
     * @return array<string, string> datum => reden
     */
    private function closedDays(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return ClosedDay::query()
            // whereDate: SQLite bewaart een date-cast als 'Y-m-d H:i:s'.
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->get()
            ->mapWithKeys(fn (ClosedDay $d) => [$d->date->toDateString() => $d->reason])
            ->all();
    }
}
