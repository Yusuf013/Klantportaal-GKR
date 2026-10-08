<?php

namespace App\Enums;

/**
 * De bestaande statuswaarden van een afspraak, nu op één plek (FR-08, ADR-011).
 *
 * De string-waarden zijn exact de waarden die al in de database en in de Blade-views staan,
 * dus er is geen datamigratie nodig. Bewust géén enum-cast op `Appointment::$status`: de
 * bestaande views vergelijken `status` met platte strings (`=== 'Voorstel'`,
 * `->where('status', 'Bevestigd')`), en een cast zou die stil laten falen (FR-10: website
 * niet herontwerpen). Schrijf en query daarom met `AppointmentStatus::X->value`.
 */
enum AppointmentStatus: string
{
    case InAfwachting = 'In afwachting';        // klant heeft een moment aangevraagd
    case Voorstel = 'Voorstel';                 // GKR heeft 1-3 momenten voorgesteld
    case BevestigdDoorKlant = 'Bevestigd door klant';
    case AlternatiefGekozen = 'Alternatief gekozen';
    case Bevestigd = 'Bevestigd';               // definitief; staat in Outlook
    case Geannuleerd = 'Geannuleerd';

    /**
     * Statussen waarbij het moment vastligt en niemand anders het meer mag boeken.
     *
     * @return list<string>
     */
    public static function blockingValues(): array
    {
        return [
            self::BevestigdDoorKlant->value,
            self::AlternatiefGekozen->value,
            self::Bevestigd->value,
        ];
    }

    /**
     * Statussen die nog op een beslissing wachten: tellen als "voorlopig" (waarschuwing, geen
     * blokkade) bij het plannen door collega's.
     *
     * @return list<string>
     */
    public static function tentativeValues(): array
    {
        return [self::InAfwachting->value, self::Voorstel->value];
    }

    /**
     * Statussen die een admin definitief kan bevestigen.
     *
     * @return list<string>
     */
    public static function approvableValues(): array
    {
        return [
            self::InAfwachting->value,
            self::BevestigdDoorKlant->value,
            self::AlternatiefGekozen->value,
        ];
    }
}
