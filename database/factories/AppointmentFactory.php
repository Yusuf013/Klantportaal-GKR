<?php

namespace Database\Factories;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    public function definition(): array
    {
        $start = CarbonImmutable::parse('next monday 10:00', config('app.timezone'));

        return [
            'project_id' => Project::factory(),
            'user_id' => fn (array $attributes) => Project::find($attributes['project_id'])->user_id,
            'title' => fake()->sentence(3),
            'type' => Appointment::TYPE_ONLINE,
            'start_time' => $start,
            'end_time' => $start->addHour(),
            'status' => AppointmentStatus::InAfwachting->value,
        ];
    }

    public function status(AppointmentStatus $status): static
    {
        return $this->state(fn () => ['status' => $status->value]);
    }

    public function at(CarbonImmutable $start, int $minutes = 60): static
    {
        return $this->state(fn () => ['start_time' => $start, 'end_time' => $start->addMinutes($minutes)]);
    }
}
