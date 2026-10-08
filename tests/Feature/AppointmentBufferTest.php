<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Project;
use App\Models\User;
use App\Services\AppointmentAvailability;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Test de regels die GKR op 8 oktober 2026 heeft bevestigd:
 * - voor en na een online of telefonische afspraak blijft 30 minuten vrij;
 * - voor en na een afspraak op kantoor blijft een uur vrij;
 * - een afspraak op kantoor begint niet voor 09:30 (in het portaal: vanaf 10:00).
 *
 * Omdat de tijdslots precies op elkaar aansluiten, betekent dit in de praktijk:
 * een medewerker krijgt nooit twee afspraken direct na elkaar.
 */
class AppointmentBufferTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://outlook.office365.com/owa/calendar/test@example.com/geheim123/calendar.ics';

    // ---------- Hulpfuncties ----------

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function client(): User
    {
        return User::factory()->create(['is_admin' => false]);
    }

    private function project(User $client): Project
    {
        return Project::forceCreate(['user_id' => $client->id, 'name' => 'Testproject']);
    }

    // Altijd een doordeweekse dag in de toekomst
    private function weekday(): string
    {
        return now()->next(Carbon::TUESDAY)->format('Y-m-d');
    }

    private function appointment(User $employee, string $slot, string $status = 'Bevestigd', string $type = 'online', ?User $client = null): Appointment
    {
        [$from, $to] = explode(' - ', $slot);
        $client ??= $this->client();
        $date = $this->weekday();

        $appointment = Appointment::create([
            'user_id'    => $client->id,
            'project_id' => $this->project($client)->id,
            'title'      => 'Testgesprek',
            'type'       => $type,
            'start_time' => "{$date} {$from}:00",
            'end_time'   => "{$date} {$to}:00",
            'status'     => $status,
        ]);

        $appointment->attendees()->attach($employee->id);

        return $appointment;
    }

    /**
     * Vraagt de kalender of een tijdslot vrij is. Geeft 'conflict' of 'available' terug.
     */
    private function slotStatus(User $employee, string $slot, ?string $type = null): string
    {
        return $this->actingAs($this->client())
            ->postJson(route('client.appointments.check'), array_filter([
                'employee_id' => $employee->id,
                'date'        => $this->weekday(),
                'time_slot'   => $slot,
                'type'        => $type,
            ]))
            ->assertOk()
            ->json('status');
    }

    private function book(User $client, User $employee, string $slot, string $type = 'online')
    {
        return $this->actingAs($client)->post(route('client.appointments.store'), [
            'project_id' => $this->project($client)->id,
            'type'       => $type,
            'title'      => 'Test',
            'date'       => $this->weekday(),
            'time_slot'  => $slot,
            'employees'  => [$employee->id],
        ]);
    }

    // ---------- 1. Nooit twee afspraken direct na elkaar ----------

    public function test_tijdslot_direct_voor_en_na_een_afspraak_is_bezet(): void
    {
        $employee = $this->admin();
        $this->appointment($employee, '10:00 - 11:00', 'In afwachting');

        $this->assertSame('conflict', $this->slotStatus($employee, '09:00 - 10:00'));
        $this->assertSame('conflict', $this->slotStatus($employee, '10:00 - 11:00'));
        $this->assertSame('conflict', $this->slotStatus($employee, '11:00 - 12:00'));
        $this->assertSame('available', $this->slotStatus($employee, '13:00 - 14:00'));
    }

    public function test_lunchpauze_is_genoeg_ruimte_tussen_twee_afspraken(): void
    {
        $employee = $this->admin();
        $this->appointment($employee, '11:00 - 12:00');

        // Tussen 12:00 en 13:00 zit een uur: genoeg voor online (30 min) én voor op kantoor (60 min)
        $this->assertSame('available', $this->slotStatus($employee, '13:00 - 14:00', 'online'));
        $this->assertSame('available', $this->slotStatus($employee, '13:00 - 14:00', 'fysiek'));
    }

    public function test_na_een_afspraak_op_kantoor_is_de_lunchpauze_ook_genoeg(): void
    {
        $employee = $this->admin();
        $this->appointment($employee, '11:00 - 12:00', 'Bevestigd', 'fysiek');

        $this->assertSame('available', $this->slotStatus($employee, '13:00 - 14:00'));
    }

    public function test_afspraak_van_een_collega_houdt_het_tijdslot_niet_bezet(): void
    {
        $this->appointment($this->admin(), '10:00 - 11:00');

        $this->assertSame('available', $this->slotStatus($this->admin(), '11:00 - 12:00'));
    }

    public function test_klant_kan_niet_direct_na_een_andere_afspraak_boeken(): void
    {
        $employee = $this->admin();
        $this->appointment($employee, '10:00 - 11:00');

        $this->book($this->client(), $employee, '11:00 - 12:00')
            ->assertSessionHasErrors('time_slot');

        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_klant_kan_geen_alternatief_direct_na_een_andere_afspraak_kiezen(): void
    {
        $employee = $this->admin();
        $client = $this->client();
        $this->appointment($employee, '10:00 - 11:00');
        $proposal = $this->appointment($employee, '14:00 - 15:00', 'Voorstel', 'online', $client);

        $this->actingAs($client)
            ->postJson(route('client.appointments.suggest-alternative', $proposal), [
                'date' => $this->weekday(), 'time_slot' => '11:00 - 12:00',
            ])
            ->assertStatus(422);

        $this->assertSame('Voorstel', $proposal->fresh()->status);
    }

    public function test_eerder_gemaakte_aansluitende_afspraken_kunnen_nog_worden_goedgekeurd(): void
    {
        // Deze twee zijn gemaakt voordat de bufferregel bestond
        Mail::fake();
        $employee = $this->admin();
        $this->appointment($employee, '10:00 - 11:00');
        $pending = $this->appointment($employee, '11:00 - 12:00', 'In afwachting');

        $this->actingAs($this->admin())->patch(route('admin.appointments.approve', $pending));

        $this->assertSame('Bevestigd', $pending->fresh()->status);
    }

    // ---------- 2. Op kantoor niet om 09:00 ----------

    public function test_afspraak_op_kantoor_kan_niet_om_negen_uur(): void
    {
        $this->book($this->client(), $this->admin(), '09:00 - 10:00', 'fysiek')
            ->assertSessionHasErrors(['time_slot' => AppointmentAvailability::OFFICE_TOO_EARLY_MESSAGE]);

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_afspraak_op_kantoor_kan_wel_om_tien_uur(): void
    {
        $this->book($this->client(), $this->admin(), '10:00 - 11:00', 'fysiek')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_online_en_telefonisch_kunnen_wel_om_negen_uur(): void
    {
        $this->book($this->client(), $this->admin(), '09:00 - 10:00', 'online')->assertSessionHasNoErrors();
        $this->book($this->client(), $this->admin(), '09:00 - 10:00', 'telefoon')->assertSessionHasNoErrors();

        $this->assertDatabaseCount('appointments', 2);
    }

    public function test_kalender_toont_negen_uur_als_bezet_voor_een_afspraak_op_kantoor(): void
    {
        $employee = $this->admin();

        $this->assertSame('conflict', $this->slotStatus($employee, '09:00 - 10:00', 'fysiek'));
        $this->assertSame('available', $this->slotStatus($employee, '09:00 - 10:00', 'online'));
        $this->assertSame('available', $this->slotStatus($employee, '10:00 - 11:00', 'fysiek'));
    }

    public function test_admin_kan_geen_afspraak_op_kantoor_om_negen_uur_voorstellen(): void
    {
        $client = $this->client();

        $this->actingAs($this->admin())->post(route('admin.appointments.store'), [
            'client_id'      => $client->id,
            'project_id'     => $this->project($client)->id,
            'title'          => 'Voorstel',
            'type'           => 'fysiek',
            'employees'      => [$this->admin()->id],
            'proposal_dates' => [['date' => $this->weekday(), 'time_slot' => '09:00 - 10:00']],
        ])->assertSessionHasErrors('proposal_dates');

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_alternatief_voor_een_afspraak_op_kantoor_kan_niet_om_negen_uur(): void
    {
        $client = $this->client();
        $proposal = $this->appointment($this->admin(), '14:00 - 15:00', 'Voorstel', 'fysiek', $client);

        $this->actingAs($client)
            ->postJson(route('client.appointments.suggest-alternative', $proposal), [
                'date' => $this->weekday(), 'time_slot' => '09:00 - 10:00',
            ])
            ->assertStatus(422)
            ->assertJson(['message' => AppointmentAvailability::OFFICE_TOO_EARLY_MESSAGE]);

        $this->assertSame('Voorstel', $proposal->fresh()->status);
    }

    // ---------- 3. Buffer rond afspraken in Outlook ----------

    /**
     * Outlook antwoordt met een agenda waarin één afspraak staat op de testdag.
     */
    private function employeeWithOutlookAppointment(string $from, string $to): User
    {
        $day = str_replace('-', '', $this->weekday());

        Http::fake([
            'outlook.office365.com/*' => Http::response(implode("\r\n", [
                'BEGIN:VCALENDAR',
                'VERSION:2.0',
                'PRODID:Microsoft Exchange Server 2010',
                'BEGIN:VEVENT',
                'UID:outlook-afspraak-1',
                'SUMMARY:Bezet',
                "DTSTART;TZID=W. Europe Standard Time:{$day}T{$from}",
                "DTEND;TZID=W. Europe Standard Time:{$day}T{$to}",
                'DTSTAMP:20261001T080000Z',
                'X-MICROSOFT-CDO-BUSYSTATUS:BUSY',
                'END:VEVENT',
                'END:VCALENDAR',
            ]) . "\r\n", 200, ['Content-Type' => 'text/calendar']),
        ]);

        $employee = $this->admin();
        $employee->outlook_ics_url = self::URL;
        $employee->save();

        return $employee;
    }

    public function test_afspraak_in_outlook_tot_negen_uur_houdt_het_eerste_tijdslot_bezet(): void
    {
        // Bijvoorbeeld een overleg van 08:30 tot 09:00: daarna moet 30 minuten vrij blijven
        $employee = $this->employeeWithOutlookAppointment('083000', '090000');

        $this->assertSame('conflict', $this->slotStatus($employee, '09:00 - 10:00', 'online'));
        $this->assertSame('available', $this->slotStatus($employee, '10:00 - 11:00', 'online'));
    }

    public function test_afspraak_in_outlook_die_een_half_uur_eerder_eindigt_blokkeert_niet(): void
    {
        $employee = $this->employeeWithOutlookAppointment('080000', '083000');

        $this->assertSame('available', $this->slotStatus($employee, '09:00 - 10:00', 'online'));
    }
}