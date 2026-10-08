<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Een GKR-medewerker zoals klant en app hem zien: naam en agendakleur. Geen e-mailadres; dat
 * heeft de app niet nodig (dataminimalisatie).
 *
 * @mixin User
 */
class EmployeeResource extends JsonResource
{
    /** Kale JSON zonder `data`-envelope, zoals de bestaande API-endpoints. */
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'color' => $this->calendarColorHex(),
        ];
    }
}
