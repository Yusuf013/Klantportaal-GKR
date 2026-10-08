<?php

namespace Tests\Feature\Calendar;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Elke GKR-medewerker een eigen kleur in de app (ADR-011).
 */
class CalendarColorTest extends TestCase
{
    use RefreshDatabase;

    public function test_ids_that_are_eight_apart_still_get_different_colours(): void
    {
        // Met de oude verdeling (id % 8) kregen deze twee dezelfde kleur.
        $a = User::factory()->admin()->create(['id' => 3]);
        $b = User::factory()->admin()->create(['id' => 11]);

        $this->assertNotSame($a->calendarColorPreset(), $b->calendarColorPreset());
    }

    public function test_the_whole_team_gets_distinct_colours(): void
    {
        $team = collect(['owen', 'noah', 'stijn', 'york', 'bo'])
            ->map(fn ($name) => User::factory()->admin()->create(['email' => "{$name}@gkr.nl"]));

        $presets = $team->map(fn (User $u) => $u->calendarColorPreset());

        $this->assertCount(5, $presets->unique());
    }

    public function test_a_chosen_colour_is_kept_and_not_given_to_a_colleague(): void
    {
        $owen = User::factory()->admin()->create();
        $noah = User::factory()->admin()->create();
        $first = config('calendar.default_palette')[0];

        // Noah kiest de kleur die Owen automatisch had.
        $noah->forceFill(['calendar_color' => $first])->save();

        $this->assertSame($first, $noah->fresh()->calendarColorPreset());
        $this->assertNotSame($first, $owen->fresh()->calendarColorPreset());
    }

    public function test_clients_do_not_take_up_colours(): void
    {
        $owen = User::factory()->admin()->create();
        User::factory()->count(3)->create();
        $noah = User::factory()->admin()->create();

        $palette = config('calendar.default_palette');
        $this->assertSame($palette[0], $owen->calendarColorPreset());
        $this->assertSame($palette[1], $noah->calendarColorPreset());
    }

    public function test_the_employee_list_shows_each_colour_once(): void
    {
        foreach (['owen', 'noah', 'stijn', 'york', 'bo'] as $name) {
            User::factory()->admin()->create(['email' => "{$name}@gkr.nl"]);
        }
        Sanctum::actingAs(User::factory()->create());

        $colors = collect($this->getJson('/api/employees')->assertOk()->json())->pluck('color');

        $this->assertCount(5, $colors->unique());
    }
}
