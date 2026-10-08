<?php

namespace App\Contracts;

use App\Services\Calendar\BusyPeriod;
use App\Services\Calendar\CalendarBlock;
use App\Services\Calendar\CalendarCategory;
use App\Services\Calendar\CalendarMeeting;
use App\Services\Calendar\CalendarMeetingResult;
use App\Services\Calendar\CalendarRejected;
use App\Services\Calendar\CalendarTemporarilyUnavailable;
use Carbon\CarbonImmutable;

/**
 * Wat het afsprakendomein van een agenda nodig heeft (FR-08, ADR-011).
 *
 * Bewust in eigen woorden gedefinieerd, niet in die van Microsoft Graph: de domeincode kent geen
 * vendor-types of -foutklassen (skill `external-integration`). Daardoor is de koppeling
 * vervangbaar en is het domein te testen tegen FakeCalendarProvider.
 *
 * Elke methode kan gooien:
 * - CalendarTemporarilyUnavailable: tijdelijk probleem (timeout, 429, 5xx); later opnieuw proberen.
 * - CalendarRejected: blijvend probleem (rechten, configuratie, onbekende mailbox).
 */
interface CalendarProvider
{
    /**
     * Maak de afspraak aan in de agenda van de organisator, of werk hem bij als
     * `$meeting->existingId` gezet is. De agenda verstuurt zelf de uitnodigingen.
     *
     * @throws CalendarTemporarilyUnavailable|CalendarRejected
     */
    public function upsertMeeting(CalendarMeeting $meeting): CalendarMeetingResult;

    /**
     * Annuleer de afspraak; deelnemers krijgen een annulering. Een al verdwenen afspraak is geen fout.
     *
     * @throws CalendarTemporarilyUnavailable|CalendarRejected
     */
    public function cancelMeeting(string $organizerEmail, string $externalId, ?string $comment = null): void;

    /**
     * Zet een "bezet"-blok (reistijd) in één agenda; geeft het id terug.
     *
     * @throws CalendarTemporarilyUnavailable|CalendarRejected
     */
    public function upsertBlock(CalendarBlock $block): string;

    /**
     * Verwijder een blok. Een al verdwenen blok is geen fout.
     *
     * @throws CalendarTemporarilyUnavailable|CalendarRejected
     */
    public function deleteBlock(string $mailbox, string $externalId): void;

    /**
     * Bezette tijden per mailbox in het gegeven tijdvak.
     *
     * @param  list<string>  $emails
     * @return array<string, list<BusyPeriod>> sleutel = e-mailadres in kleine letters
     *
     * @throws CalendarTemporarilyUnavailable|CalendarRejected
     */
    public function busyPeriods(array $emails, CarbonImmutable $from, CarbonImmutable $to): array;

    /**
     * Zoek de kopie van een afspraak in de overzichtsagenda, accepteer die stil en geef hem de
     * kleur(en) van de medewerker(s). Geeft false als de kopie er (nog) niet is.
     *
     * @param  list<CalendarCategory>  $categories
     *
     * @throws CalendarTemporarilyUnavailable|CalendarRejected
     */
    public function markInOverview(string $overviewMailbox, string $iCalUId, array $categories): bool;

    /**
     * Controleer per mailbox of de koppeling erbij kan. Voor `php artisan calendar:check`.
     *
     * @param  list<string>  $emails
     * @return array<string, ?string> e-mailadres => null als het goed is, anders een foutomschrijving
     *
     * @throws CalendarTemporarilyUnavailable|CalendarRejected
     */
    public function checkMailboxes(array $emails): array;
}
