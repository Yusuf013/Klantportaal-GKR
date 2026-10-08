<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * De enige plek die bepaalt wat er van branding naar buiten gaat. `GET /api/branding` is
 * publiek (het inlogscherm heeft het nodig), dus alleen wat toch al op dat scherm staat:
 * naam, kleuren, logo en een versie. Nooit `id`, `klant_id` of `created_at` (ADR-010).
 *
 * @mixin \App\Models\Branding
 */
class BrandingResource extends JsonResource
{
    /** Kale JSON zonder `data`-envelope, zoals de bestaande API-endpoints. */
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'organization_name' => $this->organization_name,
            'primary_color' => $this->primary_color,
            'accent_color' => $this->accent_color,
            'logo_path' => $this->logoPublicPath(),
            'updated_at' => $this->updated_at,
        ];
    }
}
