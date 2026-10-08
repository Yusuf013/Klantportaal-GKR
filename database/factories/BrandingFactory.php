<?php

namespace Database\Factories;

use App\Models\Branding;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Een bewust níet-default huisstijl, zodat tests aantonen dat geconfigureerde waarden
 * de defaults vervangen.
 *
 * @extends Factory<Branding>
 */
class BrandingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_name' => 'Acme Bouw',
            'primary_color' => '#431407',
            'accent_color' => '#EA580C',
        ];
    }
}
