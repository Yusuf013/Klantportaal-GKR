<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentOption;
use App\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Test dat bezette tijden uit Outlook meetellen bij het plannen van afspraken.
 * Er gaat nooit een echt verzoek naar Microsoft: Http::fake() doet alsof Outlook antwoordt.
 *
 * In elke test staat er in de Outlook-agenda van de medewerker één afspraak:
 * op de eerstvolgende dinsdag van 10:00 tot 11:00.
 */
class AppointmentOutlookTest extends TestCase
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

    /**
     * Een medewerker met een gekoppelde Outlook-agenda.
     */
    private function employeeWithOutlook(): User
    {
        $employee = $this->admin();
        $employee->outlook_ics_url = self::URL;
        $employee->save();

        return $employee;
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

    /**
     * Outlook antwoordt met een agenda waarin één afspraak staat (standaard dinsdag 10:00 - 11:00).
     */
    private function fakeOutlook(string $from = '100000', string $to = '110000', string $busyStatus = 'BUSY'): void
    {
        $day = str_replace('-', '', $this->weekday());

        $calendar = implode("\r\n", [
            'BEGIN:VCALENDAR',
            'METHOD:PUBLISH',
            'PRODID:Microsoft Exchange Server 2010',
            'VERSION:2.0',
            'BEGIN:VEVENT',
            'UID:outlook-afspraak-1',
            'SUMMARY:Bezet',
            "DTSTART;TZID=W. Europe Standard Time:{$day}T{$from}",
            "DTEND;TZID=W. Europe Standard Time:{$day}T{$to}",
            'DTSTAMP:20261001T080000Z',
            "X-MICROSOFT-CDO-BUSYSTATUS:{$busyStatus}",
            'END:VEVENT',
            'END:VCALENDAR',
        ]) . "\r\n";

        Http::fake([
            'outlook.office365.com/*' => Http::response($calendar, 200, ['Content-Type' => 'text/calendar']),
        ]);
    }

    private function check(User $employee, string $slot)
    {
        return $this->actingAs($this->client())
            ->postJson(route('client.appointments.check'), [
                'employee_id' => $employee->id,
                'date'        => $this->weekday(),
                'time_slot'   => $slot,
            ]);
    }

    private function appointment(User $client, string $status, string $slot, array $employees): Appointment
    {
        [$from, $to] = explode(' - ', $slot);
        $date = $this->weekday();

        $appointment = Appointment::create([
            'user_id'    => $client->id,
            'project_id' => $this->project($client)->id,
            'title'      => 'Testgesprek',
            'type'       => 'online',
            'start_time' => "{$date} {$from}:00",
            'end_time'   => "{$date} {$to}:00",
            'status'     => $status,
        ]);

        $appointment->attendees()->attach(collect($employees)->pluck('id')->all());

        return $appointment;
    }

    // ---------- 1. Beschikbaarheid in de kalender ----------

    public function test_tijdslot_is_bezet_als_er_in_outlook_iets_staat(): void
    {
        $this->fakeOutlook();
        $employee = $this->employeeWithOutlook();

        // Precies deze twee velden: de klant krijgt geen enkele reden of detail te zien
        $this->check($employee, '10:00 - 11:00')
            ->assertOk()
            ->assertExactJson(['status' => 'conflict', 'message' => 'Bezet']);
    }

    public function test_ander_tijdslot_op_dezelfde_dag_blijft_vrij(): void
    {
        $this->fakeOutlook();
        $employee = $this->employeeWithOutlook();

        $this->check($employee, '13:00 - 14:00')
            ->assertOk()
            ->assertJson(['status' => 'available']);
    }

    public function test_beschikbaar_in_outlook_houdt_het_tijdslot_niet_bezet(): void
    {
        $this->fakeOutlook(busyStatus: 'FREE');
        $employee = $this->employeeWithOutlook();

        $this->check($employee, '10:00 - 11:00')->assertJson(['status' => 'available']);
    }

    public function test_medewerker_zonder_koppeling_wordt_niet_bij_outlook_opgevraagd(): void
    {
        Http::fake();

        $this->check($this->admin(), '10:00 - 11:00')->assertJson(['status' => 'available']);

        Http::assertNothingSent();
    }

    // ---------- 2. Nieuw moment kiezen: Outlook telt mee ----------

    public function test_klant_kan_niet_boeken_op_een_moment_dat_in_outlook_bezet_is(): void
    {
        $this->fakeOutlook();
        $employee = $this->employeeWithOutlook();
        $client = $this->client();

        $this->actingAs($client)->post(route('client.appointments.store'), [
            'project_id' => $this->project($client)->id,
            'type'       => 'online',
            'title'      => 'Test',
            'date'       => $this->weekday(),
            'time_slot'  => '10:00 - 11:00',
            'employees'  => [$employee->id],
        ])->assertSessionHasErrors('time_slot');

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_klant_kan_wel_boeken_op_een_moment_dat_in_outlook_vrij_is(): void
    {
        $this->fakeOutlook();
        $employee = $this->employeeWithOutlook();
        $client = $this->client();

        $this->actingAs($client)->post(route('client.appointments.store'), [
            'project_id' => $this->project($client)->id,
            'type'       => 'online',
            'title'      => 'Test',
            'date'       => $this->weekday(),
            'time_slot'  => '13:00 - 14:00',
            'employees'  => [$employee->id],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_admin_kan_geen_moment_voorstellen_dat_in_outlook_bezet_is(): void
    {
        $this->fakeOutlook();
        $employee = $this->employeeWithOutlook();
        $client = $this->client();

        $this->actingAs($this->admin())->post(route('admin.appointments.store'), [
            'client_id'      => $client->id,
            'project_id'     => $this->project($client)->id,
            'title'          => 'Voorstel',
            'type'           => 'online',
            'employees'      => [$employee->id],
            'proposal_dates' => [['date' => $this->weekday(), 'time_slot' => '10:00 - 11:00']],
        ])->assertSessionHasErrors('proposal_dates');

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_klant_kan_geen_alternatief_kiezen_dat_in_outlook_bezet_is(): void
    {
        $this->fakeOutlook();
        $employee = $this->employeeWithOutlook();
        $client = $this->client();
        $proposal = $this->appointment($client, 'Voorstel', '14:00 - 15:00', [$employee]);

        $this->actingAs($client)
            ->postJson(route('client.appointments.suggest-alternative', $proposal), [
                'date' => $this->weekday(), 'time_slot' => '10:00 - 11:00',
            ])
            ->assertStatus(422);

        $this->assertSame('Voorstel', $proposal->fresh()->status);
    }

    // ---------- 3. Afgesproken moment bevestigen: Outlook blokkeert niet ----------

    public function test_klant_kan_een_voorgesteld_moment_bevestigen_ook_als_het_al_in_outlook_staat(): void
    {
        // GKR heeft dinsdag 10:00 voorgesteld en dat moment zelf al in Outlook gezet
        $this->fakeOutlook();
        $employee = $this->employeeWithOutlook();
        $client = $this->client();
        $proposal = $this->appointment($client, 'Voorstel', '10:00 - 11:00', [$employee]);
        $option = AppointmentOption::create([
            'appointment_id' => $proposal->id,
            'start_time'     => $this->weekday() . ' 10:00:00',
            'end_time'       => $this->weekday() . ' 11:00:00',
        ]);

        $this->actingAs($client)
            ->postJson(route('client.appointments.confirmSlot', $proposal), ['option_id' => $option->id])
            ->assertOk()
            ->assertJson(['status' => 'success']);

        $this->assertSame('Bevestigd door klant', $proposal->fresh()->status);
    }

    public function test_goedkeuren_wordt_niet_geblokkeerd_door_outlook_maar_geeft_een_waarschuwing(): void
    {
        Mail::fake();
        $this->fakeOutlook();
        $employee = $this->employeeWithOutlook();
        $pending = $this->appointment($this->client(), 'In afwachting', '10:00 - 11:00', [$employee]);

        $this->actingAs($this->admin())
            ->patch(route('admin.appointments.approve', $pending))
            ->assertSessionHas('success', fn ($message) => str_contains($message, 'Outlook-agenda van ' . $employee->name));

        $this->assertSame('Bevestigd', $pending->fresh()->status);
    }

    public function test_goedkeuren_zonder_iets_in_outlook_geeft_geen_waarschuwing(): void
    {
        Mail::fake();
        $this->fakeOutlook();
        $employee = $this->employeeWithOutlook();
        $pending = $this->appointment($this->client(), 'In afwachting', '13:00 - 14:00', [$employee]);

        $this->actingAs($this->admin())
            ->patch(route('admin.appointments.approve', $pending))
            ->assertSessionHas('success', fn ($message) => ! str_contains($message, 'Outlook'));

        $this->assertSame('Bevestigd', $pending->fresh()->status);
    }

    // ---------- 4. Als Outlook niet werkt ----------

    public function test_als_outlook_onbereikbaar_is_blijft_boeken_mogelijk(): void
    {
        Http::fake(['outlook.office365.com/*' => Http::failedConnection()]);
        $employee = $this->employeeWithOutlook();

        $this->check($employee, '10:00 - 11:00')->assertOk()->assertJson(['status' => 'available']);
        $this->check($employee, '11:00 - 12:00')->assertOk()->assertJson(['status' => 'available']);

        // Na een mislukte poging proberen we het een minuut lang niet opnieuw
        Http::assertSentCount(1);
    }

    public function test_onleesbare_opgeslagen_link_blokkeert_niets(): void
    {
        Http::fake();
        $employee = $this->admin();
        // Rechtstreeks in de database gezet, dus niet geldig versleuteld
        // (dit gebeurt in het echt als APP_KEY verandert)
        DB::table('users')->where('id', $employee->id)->update(['outlook_ics_url' => 'niet-te-ontsleutelen']);

        $this->check($employee, '10:00 - 11:00')->assertOk()->assertJson(['status' => 'available']);

        Http::assertNothingSent();
    }
}