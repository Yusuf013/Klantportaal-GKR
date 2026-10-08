<?php

namespace Tests\Feature\Appointments;

use App\Contracts\CalendarProvider;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Project;
use App\Models\User;
use App\Services\Calendar\FakeCalendarProvider;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gedeelde opzet voor de afspraaktests (FR-08, ADR-011): een vaste "nu" (woensdag 7 oktober
 * 2026, 08:00) en de in-memory agenda in plaats van Outlook.
 */
abstract class SchedulingTestCase extends TestCase
{
    use RefreshDatabase;

    protected FakeCalendarProvider $calendar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-07 08:00', 'Europe/Amsterdam'));
        config(['calendar.driver' => 'fake', 'calendar.overview_mailbox' => 'info@gkr.nl']);

        $this->calendar = new FakeCalendarProvider;
        $this->app->instance(CalendarProvider::class, $this->calendar);
    }

    /**
     * Maandag 12 oktober 2026 op het gegeven uur.
     */
    protected function monday(string $time = '10:00'): CarbonImmutable
    {
        return CarbonImmutable::parse('2026-10-12 '.$time, 'Europe/Amsterdam');
    }

    protected function employee(string $name, string $email): User
    {
        return User::factory()->admin()->create(['name' => $name, 'email' => $email]);
    }

    protected function client(string $name = 'Bakkerij Jansen'): User
    {
        return User::factory()->create(['name' => $name]);
    }

    protected function projectFor(User $client): Project
    {
        return Project::factory()->create(['user_id' => $client->id, 'name' => 'Website Redesign 2026']);
    }

    /**
     * Een voorstel van `$by` aan `$client` met de gegeven opties.
     *
     * @param  list<CarbonImmutable>  $options
     */
    protected function proposal(User $by, User $client, array $options, array $overrides = []): Appointment
    {
        $appointment = Appointment::factory()
            ->status(AppointmentStatus::Voorstel)
            ->at($options[0])
            ->create(array_merge([
                'project_id' => $this->projectFor($client)->id,
                'user_id' => $client->id,
                'organizer_user_id' => $by->id,
            ], $overrides));

        $appointment->attendees()->sync([$by->id]);

        foreach ($options as $start) {
            $appointment->options()->create(['start_time' => $start, 'end_time' => $start->addMinutes($appointment->durationMinutes())]);
        }

        return $appointment;
    }

    protected function confirmed(User $employee, User $client, CarbonImmutable $start, array $overrides = []): Appointment
    {
        $appointment = Appointment::factory()
            ->status(AppointmentStatus::Bevestigd)
            ->at($start)
            ->create(array_merge([
                'project_id' => $this->projectFor($client)->id,
                'user_id' => $client->id,
                'organizer_user_id' => $employee->id,
            ], $overrides));

        $appointment->attendees()->sync([$employee->id]);

        return $appointment;
    }
}
