<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Wie mag welke afspraak zien of wijzigen? (ADR-006: autorisatie in Policies, gedeeld door
 * website en API.)
 *
 * Scoping is nog op `user_id` (de klant van de afspraak), niet op een Klant-model/KlantScope
 * (ADR-001, aparte vervolgstap). Een afspraak van iemand anders geeft altijd 404, nooit 403:
 * het bestaan van een vreemd id mag niet waarneembaar zijn (conventie uit Api/DocumentController).
 */
class AppointmentPolicy
{
    public function view(User $user, Appointment $appointment): Response
    {
        return $user->isAdmin() || $appointment->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Voorstel bevestigen, alternatief kiezen of annuleren: alleen de klant zelf.
     */
    public function respond(User $user, Appointment $appointment): Response
    {
        return ! $user->isAdmin() && $appointment->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Definitief bevestigen of afwijzen: alleen GKR-medewerkers.
     */
    public function manage(User $user, Appointment $appointment): Response
    {
        return $user->isAdmin() ? Response::allow() : Response::denyAsNotFound();
    }
}
