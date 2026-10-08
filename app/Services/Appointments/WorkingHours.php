<?php

namespace App\Services\Appointments;

use App\Models\ClosedDay;
use Carbon\CarbonImmutable;

/**
 * De werktijden uit `config/appointments.php` (stap 4a, ADR-011): standaard ma-vr 09:00-17:00.
 * Blokken waarin niets in de agenda staat, zijn beschikbaar.
 */
class WorkingHours
{
    public function isWorkingDay(CarbonImmutable $date): bool
    {
        return in_array($date->dayOfWeekIso, config('appointments.working_days'), true);
    }

    /**
     * Welke dagen de datumkiezers op de website aanbieden: dezelfde bron als de controle bij het
     * opslaan (werkdagen uit de config en gesloten dagen), niet een vast weekend in JavaScript.
     *
     * @return array{working_days: list<int>, closed: list<string>}
     */
    public function calendarDays(): array
    {
        return [
            'working_days' => config('appointments.working_days'),
            'closed' => ClosedDay::query()->whereDate('date', '>=', today())->orderBy('date')->pluck('date')
                ->map(fn ($date) => $date->format('Y-m-d'))->values()->all(),
        ];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function window(CarbonImmutable $date): array
    {
        $day = $date->setTimezone(config('app.timezone'))->startOfDay();

        return [
            $day->setTimeFromTimeString(config('appointments.day_start')),
            $day->setTimeFromTimeString(config('appointments.day_end')),
        ];
    }

    /**
     * Valt de afspraak, inclusief reistijd ervoor en erna, helemaal binnen een werkdag?
     */
    public function fits(CarbonImmutable $start, CarbonImmutable $end, int $travelMinutes = 0): bool
    {
        if (! $this->isWorkingDay($start) || ! $start->isSameDay($end)) {
            return false;
        }

        [$open, $close] = $this->window($start);

        return $start->subMinutes($travelMinutes) >= $open && $end->addMinutes($travelMinutes) <= $close;
    }

    /**
     * Alle begintijden op deze dag waarbij een afspraak van `$durationMinutes` (plus reistijd)
     * binnen de werktijd past.
     *
     * @return list<CarbonImmutable>
     */
    public function slotStarts(CarbonImmutable $date, int $durationMinutes, int $travelMinutes = 0): array
    {
        if (! $this->isWorkingDay($date)) {
            return [];
        }

        [$open, $close] = $this->window($date);
        $step = (int) config('appointments.slot_minutes');
        $starts = [];

        for ($start = $open; $start < $close; $start = $start->addMinutes($step)) {
            if ($this->fits($start, $start->addMinutes($durationMinutes), $travelMinutes)) {
                $starts[] = $start;
            }
        }

        return $starts;
    }

    /**
     * De tijdslotlabels die de website toont ("09:00 - 10:00").
     *
     * @return list<string>
     */
    public function slotLabels(int $durationMinutes = 60): array
    {
        // Een willekeurige maandag: alleen de kloktijden zijn relevant.
        $monday = CarbonImmutable::parse('2026-01-05', config('app.timezone'));

        return array_map(
            fn (CarbonImmutable $s) => $s->format('H:i').' - '.$s->addMinutes($durationMinutes)->format('H:i'),
            $this->slotStarts($monday, $durationMinutes),
        );
    }
}
