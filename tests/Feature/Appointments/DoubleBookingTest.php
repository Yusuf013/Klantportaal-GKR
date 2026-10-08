<?php

namespace Tests\Feature\Appointments;

use App\Enums\AppointmentStatus;
use App\Services\Calendar\BusyKind;
use App\Services\Calendar\BusyPeriod;
use App\Services\Calendar\CalendarTemporarilyUnavailable;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;

/**
 * Twee collega's plannen tegelijk hetzelfde moment (stap 4b, ADR-011): wie het eerst
 * vastlegt, krijgt het moment; de tweede krijgt een melding in gewone taal.
 */
class DoubleBookingTest extends SchedulingTestCase
{
    public function test_two_clients_confirming_the_same_moment_for_the_same_employee_gives_one_success_and_one_conflict(): void
    {
        $noah = $this->employee('Noah', 'noah@gkr.nl');
        $bakkerij = $this->client('Bakkerij Jansen');
        $slagerij = $this->client('Slagerij Smit');

        // Owen en York hebben allebei, los van elkaar, Noah op maandag 10:00 voorgesteld.
        $first = $this->proposal($noah, $bakkerij, [$this->monday('10:00')]);
        $second = $this->proposal($noah, $slagerij, [$this->monday('10:00'), $this->monday('13:00')]);

        Sanctum::actingAs($bakkerij);
        $this->postJson("/api/appointments/{$first->id}/confirm-option", ['option_id' => $first->options()->first()->id])
            ->assertOk();

        Sanctum::actingAs($slagerij);
        $this->postJson("/api/appointments/{$second->id}/confirm-option", ['option_id' => $second->options()->orderBy('start_time')->first()->id])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Dit moment is net door iemand anders vastgelegd. Kies een van de andere tijden of vraag een nieuw moment aan.');

        // De tweede klant kan nog wel het andere moment kiezen.
        $this->assertSame(AppointmentStatus::Voorstel->value, $second->fresh()->status);
        $this->postJson("/api/appointments/{$second->id}/confirm-option", ['option_id' => $second->options()->orderBy('start_time', 'desc')->first()->id])
            ->assertOk();
    }

    public function test_approval_is_refused_when_the_employee_became_busy_in_outlook(): void
    {
        $noah = $this->employee('Noah', 'noah@gkr.nl');
        $client = $this->client();
        $appointment = $this->proposal($noah, $client, [$this->monday('10:00')]);

        Sanctum::actingAs($client);
        $this->postJson("/api/appointments/{$appointment->id}/confirm-option", ['option_id' => $appointment->options()->first()->id])->assertOk();

        // Intussen zet Noah zelf een intern overleg in Outlook.
        $this->calendar->setBusy('noah@gkr.nl', [new BusyPeriod($this->monday('09:30'), $this->monday('10:30'), BusyKind::Busy)]);

        Sanctum::actingAs($noah);
        $this->postJson("/api/admin/appointments/{$appointment->id}/approve")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Noah is op dit moment al bezet. Kies een ander moment.');

        $this->assertSame(AppointmentStatus::BevestigdDoorKlant->value, $appointment->fresh()->status);
        $this->assertSame([], $this->calendar->activeMeetings());
    }

    public function test_the_hard_check_ignores_the_availability_cache(): void
    {
        $noah = $this->employee('Noah', 'noah@gkr.nl');
        $client = $this->client();
        $appointment = $this->proposal($noah, $client, [$this->monday('10:00')]);

        // Beschikbaarheid opvragen vult de cache met "vrij" ...
        Sanctum::actingAs($client);
        $this->getJson('/api/availability?'.http_build_query(['employee_ids' => [$noah->id], 'from' => '2026-10-12', 'to' => '2026-10-12']))->assertOk();

        // ... daarna wordt Noah bezet in Outlook. De harde controle moet dat toch zien.
        $this->calendar->setBusy('noah@gkr.nl', [new BusyPeriod($this->monday('10:00'), $this->monday('11:00'), BusyKind::Busy)]);

        $this->postJson("/api/appointments/{$appointment->id}/confirm-option", ['option_id' => $appointment->options()->first()->id])
            ->assertStatus(409);
    }

    public function test_when_a_colleague_is_booking_the_same_employee_right_now_the_second_request_is_asked_to_retry(): void
    {
        config(['appointments.lock_wait_seconds' => 0]);
        $noah = $this->employee('Noah', 'noah@gkr.nl');
        $client = $this->client();
        $appointment = $this->proposal($noah, $client, [$this->monday('10:00')]);

        $lock = Cache::lock("schedule:employee:{$noah->id}", 30);
        $this->assertTrue($lock->get());

        try {
            Sanctum::actingAs($client);
            $this->postJson("/api/appointments/{$appointment->id}/confirm-option", ['option_id' => $appointment->options()->first()->id])
                ->assertStatus(409)
                ->assertJsonPath('message', 'Iemand anders is op dit moment dezelfde agenda aan het plannen. Probeer het over een paar seconden opnieuw.');
        } finally {
            $lock->release();
        }

        $this->assertSame(AppointmentStatus::Voorstel->value, $appointment->fresh()->status);
    }

    public function test_approval_still_works_when_outlook_is_down_but_warns_the_admin(): void
    {
        $noah = $this->employee('Noah', 'noah@gkr.nl');
        $client = $this->client();
        $appointment = $this->proposal($noah, $client, [$this->monday('10:00')]);
        Sanctum::actingAs($client);
        $this->postJson("/api/appointments/{$appointment->id}/confirm-option", ['option_id' => $appointment->options()->first()->id])->assertOk();

        $this->calendar->failWith(new CalendarTemporarilyUnavailable('storing'));

        Sanctum::actingAs($noah);
        $this->postJson("/api/admin/appointments/{$appointment->id}/approve")
            ->assertOk()
            ->assertJsonPath('appointment.status', AppointmentStatus::Bevestigd->value)
            ->assertJsonPath('notice', 'We konden de Outlook-agenda nu niet controleren. Controleer zelf of het moment nog vrij is.')
            // De sync is mislukt en wordt later opnieuw geprobeerd.
            ->assertJsonPath('appointment.calendar_sync_status', 'pending');
    }
}
