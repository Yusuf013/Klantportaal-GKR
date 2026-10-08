<?php

namespace App\Services\Calendar;

use App\Contracts\CalendarProvider;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * In-memory agenda voor lokaal ontwikkelen en tests (ADR-011). Er wordt nooit een echte
 * Outlook-agenda aangeroepen.
 *
 * Tests kunnen bezette tijden instellen (`setBusy`), een fout forceren (`failWith`) en achteraf
 * controleren wat er "in Outlook" staat (`$meetings`, `$blocks`, `$overview`).
 */
class FakeCalendarProvider implements CalendarProvider
{
    /** @var array<string, array{meeting: CalendarMeeting, cancelled: bool, iCalUId: string}> */
    public array $meetings = [];

    /** @var array<string, CalendarBlock> */
    public array $blocks = [];

    /** @var array<string, list<string>> iCalUId => categorienamen */
    public array $overview = [];

    /** @var array<string, list<BusyPeriod>> */
    private array $busy = [];

    private ?Throwable $failure = null;

    private bool $overviewCopyArrived = true;

    private int $sequence = 0;

    /**
     * @param  list<BusyPeriod>  $periods
     */
    public function setBusy(string $email, array $periods): void
    {
        $this->busy[strtolower($email)] = $periods;
    }

    /**
     * Laat elke volgende aanroep falen met deze fout (null = weer normaal).
     */
    public function failWith(?Throwable $failure): void
    {
        $this->failure = $failure;
    }

    /**
     * Simuleer dat de uitnodiging nog niet in de overzichtsagenda is aangekomen.
     */
    public function overviewCopyArrives(bool $arrived): void
    {
        $this->overviewCopyArrived = $arrived;
    }

    public function upsertMeeting(CalendarMeeting $meeting): CalendarMeetingResult
    {
        $this->throwIfFailing();

        $id = $meeting->existingId !== null && isset($this->meetings[$meeting->existingId])
            ? $meeting->existingId
            : $this->findByIdempotencyKey($meeting->idempotencyKey) ?? 'fake-event-'.(++$this->sequence);

        $iCalUId = $this->meetings[$id]['iCalUId'] ?? 'fake-ical-'.$id;
        $this->meetings[$id] = ['meeting' => $meeting, 'cancelled' => false, 'iCalUId' => $iCalUId];

        return new CalendarMeetingResult(
            $id,
            $iCalUId,
            $meeting->online ? 'https://example.invalid/teams/'.$id : null,
        );
    }

    public function cancelMeeting(string $organizerEmail, string $externalId, ?string $comment = null): void
    {
        $this->throwIfFailing();

        if (isset($this->meetings[$externalId])) {
            $this->meetings[$externalId]['cancelled'] = true;
        }
    }

    public function upsertBlock(CalendarBlock $block): string
    {
        $this->throwIfFailing();

        $id = $block->existingId !== null && isset($this->blocks[$block->existingId])
            ? $block->existingId
            : 'fake-block-'.(++$this->sequence);

        $this->blocks[$id] = $block;

        return $id;
    }

    public function deleteBlock(string $mailbox, string $externalId): void
    {
        $this->throwIfFailing();

        unset($this->blocks[$externalId]);
    }

    public function busyPeriods(array $emails, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $this->throwIfFailing();

        $result = [];

        foreach ($emails as $email) {
            $result[strtolower($email)] = array_values(array_filter(
                $this->busy[strtolower($email)] ?? [],
                fn (BusyPeriod $p) => $p->overlaps($from, $to),
            ));
        }

        return $result;
    }

    public function markInOverview(string $overviewMailbox, string $iCalUId, array $categories): bool
    {
        $this->throwIfFailing();

        if (! $this->overviewCopyArrived) {
            return false;
        }

        $this->overview[$iCalUId] = array_map(fn (CalendarCategory $c) => $c->name, $categories);

        return true;
    }

    public function checkMailboxes(array $emails): array
    {
        $this->throwIfFailing();

        return array_fill_keys(array_map('strtolower', $emails), null);
    }

    /**
     * @return list<CalendarMeeting>
     */
    public function activeMeetings(): array
    {
        return array_values(array_map(
            fn (array $m) => $m['meeting'],
            array_filter($this->meetings, fn (array $m) => ! $m['cancelled']),
        ));
    }

    private function findByIdempotencyKey(string $key): ?string
    {
        foreach ($this->meetings as $id => $entry) {
            if ($entry['meeting']->idempotencyKey === $key) {
                return $id;
            }
        }

        return null;
    }

    private function throwIfFailing(): void
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }
    }
}
