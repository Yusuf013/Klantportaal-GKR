<?php

namespace App\Policies;

use App\Models\Branding;
use App\Models\User;

/**
 * Wie de huisstijl mag wijzigen. Nu: elke admin van de installatie (er is één record).
 *
 * #33: zodra er een Klant-model is, hoort hier de eigendomscheck bij —
 * `&& $user->klant_id === $branding->klant_id` — zodat een beheerder alleen de branding van
 * de eigen klant kan wijzigen (ADR-010, migratiepad stap 3).
 */
class BrandingPolicy
{
    public function update(User $user, Branding $branding): bool
    {
        return $user->isAdmin();
    }
}
