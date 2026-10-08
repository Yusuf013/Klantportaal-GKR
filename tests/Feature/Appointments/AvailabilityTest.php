<?php

namespace Tests\Feature\Appointments;

use App\Models\Appointment;
use App\Models\ClosedDay;
use App\Services\Appointments\AvailabilityService;
use App\Services\Calendar\BusyKind;
use App\Services\Calendar\BusyPeriod;
use App\Services\Calendar\CalendarTemporarilyUnavailable;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;

/**
 * Werktijden, vakanties, gesloten dagen en Outlook-bezet (stap 4 en 4a, ADR-011).
 */
class AvailabilityTest extends SchedulingTestCase
{
    private function slotsFor(array $employees, string $from, string $to, int $duration = 60, int $travel = 0): array
    {
        return app(AvailabilityService::class)->slots(
            collect($employees),
            CarbonImmutable::parse($from, 'Europe/Amsterdam'),
            CarbonImmutable::parse($to, 'Europe/Amsterdam'),
            $duration,
            $travel,
        );
    }

    private function times(array $day): array
    {
        return array_map(fn ($s) => $s['start']->format('H:i'), $day['slots']);
    }

    public function test_an_empty_agenda_offers_eight_hourly_blocks_on_a_working_day(): void
    {
        $owen = $this->employee('Owen', 'owen@gkr.nl');

        $result = $this->slotsFor([$owen], '2026-10-12', '2026-10-12');

        $this->assertSame(
            ['09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00'],
            $this->times($result['days'][0]),
        );
    }

    public function test_weekends_have_no_blocks(): void
    {
        $owen = $this->employee('Owen', 'owen@gkr.nl');

        $result = $this->slotsFor([$owen], '2026-10-10', '2026-10-11');

        $this->assertSame([], $result['days'][0]['slots']);
        $this->assertSame([], $result['days'][1]['slots']);
    }

    public function test_a_90_minute_meeting_must_end_by_five(): void
    {
        $owen = $this->employee('Owen', 'owen@gkr.nl');

        $times = $this->times($this->slotsFor([$owen], '2026-10-12', '2026-10-12', 90)['days'][0]);

        $this->assertContains('15:00', $times);
        $this->assertNotContains('16:00', $times);
    }

    public function test_travel_time_must_also_fit_within_working_hours(): void
    {
        $owen = $this->employee('Owen', 'owen@gkr.nl');

        $times = $this->times($this->slotsFor([$owen], '2026-10-12', '2026-10-12', 60, 60)['days'][0]);

        $this->assertSame(['10:00', '11:00', '12:00', '13:00', '14:00', '15:00'], $times);
    }

    public function test_an_away_item_in_outlook_removes_the_blocks_and_shows_as_away_for_admins(): void
    {
        $owen = $this->employee('Owen', 'owen@gkr.nl');
        $this->calendar->setBusy('owen@gkr.nl', [
            new BusyPeriod($this->monday('00:00'), $this->monday('00:00')->addDay(), BusyKind::Away),
        ]);

        $this->assertSame([], $this->slotsFor([$owen], '2026-10-12', '2026-10-12')['days'][0]['slots']);

        $moments = app(AvailabilityService::class)->momentStatuses(
            collect([$owen]),
            [['start' => $this->monday('10:00'), 'end' => $this->monday('11:00')]],
            60,
        );

        $this->assertSame(AvailabilityService::STATUS_AWAY, $moments['moments'][0]['employees'][0]['status']);
    }

    public function test_tentative_items_in_outlook_count_as_busy(): void
    {
        $owen = $this->employee('Owen', 'owen@gkr.nl');
        $this->calendar->setBusy('owen@gkr.nl', [
            new BusyPeriod($this->monday('10:00'), $this->monday('11:00'), BusyKind::Tentative),
        ]);

        $this->assertNotContains('10:00', $this->times($this->slotsFor([$owen], '2026-10-12', '2026-10-12')['days'][0]));
    }

    public function test_a_closed_day_has_no_blocks_and_gives_the_reason(): void
    {
        $owen = $this->employee('Owen', 'owen@gkr.nl');
        ClosedDay::create(['date' => '2026-10-12', 'reason' => 'Studiedag']);

        $day = $this->slotsFor([$owen], '2026-10-12', '2026-10-12')['days'][0];

        $this->assertSame([], $day['slots']);
        $this->assertSame('Studiedag', $day['closed_reason']);
    }

    public function test_a_confirmed_appointment_with_travel_blocks_the_surrounding_hours(): void
    {
        $owen = $this->employee('Owen', 'owen@gkr.nl');
        $this->confirmed($owen, $this->client(), $this->monday('12:00'), [
            'type' => Appointment::TYPE_FYSIEK,
            'location' => Appointment::LOCATION_OP_LOCATIE,
            'travel_minutes' => 60,
        ]);

        $times = $this->times($this->slotsFor([$owen], '2026-10-12', '2026-10-12')['days'][0]);

        $this->assertSame(['09:00', '10:00', '14:00', '15:00', '16:00'], $times);
    }

    public function test_only_moments_where_all_chosen_employees_are_free_are_offered(): void
    {
        $owen = $this->employee('Owen', 'owen@gkr.nl');
        $noah = $this->employee('Noah', 'noah@gkr.nl');
        $this->calendar->setBusy('noah@gkr.nl', [
            new BusyPeriod($this->monday('09:00'), $this->monday('12:00'), BusyKind::Busy),
        ]);

        $times = $this->times($this->slotsFor([$owen, $noah], '2026-10-12', '2026-10-12')['days'][0]);

        $this->assertSame(['12:00', '13:00', '14:00', '15:00', '16:00'], $times);
    }

    public function test_when_outlook_is_unreachable_it_falls_back_to_platform_data_and_says_so(): void
    {
        $owen = $this->employee('Owen', 'owen@gkr.nl');
        $this->calendar->failWith(new CalendarTemporarilyUnavailable('timeout'));

        $result = $this->slotsFor([$owen], '2026-10-12', '2026-10-12');

        $this->assertTrue($result['outlook_unavailable']);
        $this->assertCount(8, $result['days'][0]['slots']);
    }

    public function test_the_api_never_tells_a_client_why_an_employee_is_unavailable(): void
    {
        $owen = $this->employee('Owen', 'owen@gkr.nl');
        $this->calendar->setBusy('owen@gkr.nl', [
            new BusyPeriod($this->monday('00:00'), $this->monday('00:00')->addDay(), BusyKind::Away),
        ]);
        Sanctum::actingAs($this->client());

        $response = $this->getJson('/api/availability?'.http_build_query([
            'employee_ids' => [$owen->id], 'from' => '2026-10-12', 'to' => '2026-10-12',
        ]));

        $response->assertOk()
            ->assertJsonPath('days.0.slots', [])
            ->assertJsonPath('days.0.closed_reason', null);
        $this->assertStringNotContainsStringIgnoringCase('afwezig', $response->getContent());
    }

    public function test_a_client_cannot_request_a_range_longer_than_allowed(): void
    {
        $owen = $this->employee('Owen', 'owen@gkr.nl');
        Sanctum::actingAs($this->client());

        $this->getJson('/api/availability?'.http_build_query([
            'employee_ids' => [$owen->id], 'from' => '2026-10-12', 'to' => '2027-01-31',
        ]))->assertUnprocessable()->assertJsonValidationErrors('to');
    }

    public function test_admins_see_a_warning_when_a_colleague_already_proposed_the_moment(): void
    {
        $owen = $this->employee('Owen', 'owen@gkr.nl');
        $noah = $this->employee('Noah', 'noah@gkr.nl');
        $this->proposal($noah, $this->client('Bakkerij Jansen'), [$this->monday('10:00')]);
        $this->proposal($noah, $this->client('Slagerij Smit'), [$this->monday('14:00')], ['organizer_user_id' => $noah->id]);
        Sanctum::actingAs($owen);

        $response = $this->getJson('/api/admin/availability?'.http_build_query([
            'employee_ids' => [$noah->id],
            'moments' => [['start' => $this->monday('10:00')->toIso8601String(), 'end' => $this->monday('11:00')->toIso8601String()]],
        ]));

        $response->assertOk()
            ->assertJsonPath('moments.0.employees.0.status', 'available')
            ->assertJsonPath('moments.0.employees.0.label', 'Beschikbaar')
            ->assertJsonPath('moments.0.employees.0.warnings', ['Noah heeft dit moment al voorgesteld aan Bakkerij Jansen.']);
    }
}
