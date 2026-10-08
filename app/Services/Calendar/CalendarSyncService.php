<?php

namespace App\Services\Calendar;

use App\Contracts\CalendarProvider;
use App\Enums\AppointmentStatus;
use App\Jobs\ColorAppointmentInOverview;
use App\Models\Appointment;
use App\Models\AppointmentCalendarEvent;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Zet een afspraak in Outlook of haalt hem eruit (FR-08, ADR-011). Eén richting:
 * platform -> Outlook.
 *
 * - Bevestigd: event in de agenda van de organiserende medewerker, met de klant en de overige
 *   medewerkers als deelnemers en info@ als optionele deelnemer. Outlook verstuurt zelf de
 *   uitnodigingen, vanaf het adres van de medewerker. Bij "Op locatie" komen er reistijdblokken
 *   in de agenda's van de betrokken medewerkers.
 * - Geannuleerd: event annuleren (deelnemers krijgen een annulering) en blokken opruimen.
 */
class CalendarSyncService
{
    public function __construct(private readonly CalendarProvider $calendar) {}

    public function sync(Appointment $appointment): void
    {
        $appointment->loadMissing(['client', 'project', 'organizer', 'attendees', 'calendarEvents.user']);

        if ($appointment->hasStatus(AppointmentStatus::Bevestigd)) {
            $this->push($appointment);
        } elseif ($appointment->hasStatus(AppointmentStatus::Geannuleerd)) {
            $this->withdraw($appointment);
        }

        $appointment->forceFill([
            'calendar_sync_status' => Appointment::SYNC_SYNCED,
            'calendar_synced_at' => now(),
            'calendar_sync_error' => null,
        ])->save();
    }

    private function push(Appointment $appointment): void
    {
        $organizer = $this->organizer($appointment);
        $participants = $appointment->internalParticipants();
        $overview = config('calendar.overview_mailbox');

        $required = [new CalendarAttendee($appointment->client->email, $appointment->client->name)];

        foreach ($participants as $employee) {
            if ($employee->id !== $organizer->id) {
                $required[] = new CalendarAttendee($employee->email, $employee->name);
            }
        }

        $optional = $overview && strcasecmp($overview, $organizer->email) !== 0
            ? [new CalendarAttendee($overview, 'GKR agenda')]
            : [];

        $result = $this->calendar->upsertMeeting(new CalendarMeeting(
            organizerEmail: $organizer->email,
            subject: $appointment->title,
            body: $this->body($appointment),
            start: CarbonImmutable::parse($appointment->start_time),
            end: CarbonImmutable::parse($appointment->end_time),
            location: $appointment->locationLabel(),
            online: $appointment->type === Appointment::TYPE_ONLINE,
            requiredAttendees: $required,
            optionalAttendees: $optional,
            idempotencyKey: 'klantportaal-appointment-'.$appointment->id,
            existingId: $appointment->outlook_event_id,
        ));

        $appointment->forceFill([
            'outlook_event_id' => $result->externalId,
            'ical_uid' => $result->iCalUId ?? $appointment->ical_uid,
            'online_meeting_url' => $appointment->type === Appointment::TYPE_ONLINE
                ? ($result->joinUrl ?? $appointment->online_meeting_url)
                : null,
        ])->save();

        $this->syncTravelBlocks($appointment, $participants->all());

        if ($overview && $appointment->ical_uid) {
            ColorAppointmentInOverview::dispatch($appointment->id)->delay(now()->addSeconds(30));
        }
    }

    /**
     * @param  list<User>  $participants
     */
    private function syncTravelBlocks(Appointment $appointment, array $participants): void
    {
        $travel = $appointment->effectiveTravelMinutes();
        $start = CarbonImmutable::parse($appointment->start_time);
        $end = CarbonImmutable::parse($appointment->end_time);
        $wanted = [];

        if ($travel > 0) {
            foreach ($participants as $employee) {
                $wanted[$employee->id.'|'.AppointmentCalendarEvent::KIND_TRAVEL_BEFORE] = [$employee, $start->subMinutes($travel), $start];
                $wanted[$employee->id.'|'.AppointmentCalendarEvent::KIND_TRAVEL_AFTER] = [$employee, $end, $end->addMinutes($travel)];
            }
        }

        foreach ($appointment->calendarEvents as $existing) {
            if (! isset($wanted[$existing->user_id.'|'.$existing->kind])) {
                $this->calendar->deleteBlock($existing->user->email, $existing->external_id);
                $existing->delete();
            }
        }

        foreach ($wanted as $key => [$employee, $blockStart, $blockEnd]) {
            $kind = explode('|', $key)[1];
            $existing = $appointment->calendarEvents->first(fn ($e) => $e->user_id === $employee->id && $e->kind === $kind);

            $id = $this->calendar->upsertBlock(new CalendarBlock(
                mailbox: $employee->email,
                subject: 'Reistijd: '.$appointment->title,
                start: $blockStart,
                end: $blockEnd,
                existingId: $existing?->external_id,
            ));

            AppointmentCalendarEvent::updateOrCreate(
                ['appointment_id' => $appointment->id, 'user_id' => $employee->id, 'kind' => $kind],
                ['external_id' => $id],
            );
        }
    }

    private function withdraw(Appointment $appointment): void
    {
        if ($appointment->outlook_event_id !== null) {
            $this->calendar->cancelMeeting(
                $this->organizer($appointment)->email,
                $appointment->outlook_event_id,
                'Deze afspraak is geannuleerd.',
            );
        }

        foreach ($appointment->calendarEvents as $block) {
            $this->calendar->deleteBlock($block->user->email, $block->external_id);
            $block->delete();
        }
    }

    private function organizer(Appointment $appointment): User
    {
        $organizer = $appointment->organizer ?? $appointment->attendees->first();

        if ($organizer === null) {
            throw new CalendarRejected('Er is geen GKR-medewerker aan deze afspraak gekoppeld.');
        }

        return $organizer;
    }

    private function body(Appointment $appointment): string
    {
        return collect([
            $appointment->project ? 'Project: '.$appointment->project->name : null,
            $appointment->description,
            'Ingepland via het GKR Klantportaal.',
        ])->filter()->implode("\n\n");
    }
}
