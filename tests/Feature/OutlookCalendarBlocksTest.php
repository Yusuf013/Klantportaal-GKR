<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Services\AppointmentAvailability;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Test de grijze blokken "Bezet (Outlook)" in de adminkalender.
 * Er gaat nooit een echt verzoek naar Microsoft: Http::fake() doet alsof Outlook antwoordt.
 */
class OutlookCalendarBlocksTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://outlook.office365.com/owa/calendar/test@example.com/geheim123/calendar.ics';

    // ---------- Hulpfuncties ----------

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function employeeWithOutlook(): User
    {
        $employee = $this->admin();
        $employee->outlook_ics_url = self::URL;
        $employee->save();

        return $employee;
    }

    // Altijd een doordeweekse dag in de toekomst (binnen de 90 dagen die de kalender toont)
    private function tuesday(): Carbon
    {
        return now()->next(Carbon::TUESDAY);
    }

    /**
     * Outlook antwoordt met een agenda met deze afspraken (regels in agendaformaat).
     */
    private function fakeOutlook(array ...$events): void
    {
        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:Microsoft Exchange Server 2010'];

        foreach ($events as $index => $event) {
            array_push($lines, 'BEGIN:VEVENT', "UID:outlook-{$index}", 'SUMMARY:Bezet', 'DTSTAMP:20261001T080000Z', ...$event);
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        Http::fake([
            'outlook.office365.com/*' => Http::response(implode("\r\n", $lines) . "\r\n", 200, ['Content-Type' => 'text/calendar']),
        ]);
    }

    /**
     * Een afspraak met tijd op de testdinsdag, bijv. timed('083000', '091500').
     */
    private function timed(string $from, string $to, string $busyStatus = 'BUSY'): array
    {
        $day = $this->tuesday()->format('Ymd');

        return [
            "DTSTART;TZID=W. Europe Standard Time:{$day}T{$from}",
            "DTEND;TZID=W. Europe Standard Time:{$day}T{$to}",
            "X-MICROSOFT-CDO-BUSYSTATUS:{$busyStatus}",
        ];
    }

    private function blocks(): array
    {
        return app(AppointmentAvailability::class)->outlookCalendarBlocks();
    }

    // ---------- 1. De gegevens voor de kalender ----------

    public function test_afspraak_uit_outlook_wordt_een_blok_met_medewerker_datum_en_tijd(): void
    {
        $this->fakeOutlook($this->timed('083000', '091500'));
        $employee = $this->employeeWithOutlook();

        $this->assertSame([[
            'employee_id' => $employee->id,
            'employee'    => $employee->name,
            'date'        => $this->tuesday()->format('Y-m-d'),
            'time'        => '08:30 - 09:15',
        ]], $this->blocks());
    }

    public function test_afwezigheid_van_twee_dagen_geeft_op_beide_dagen_een_blok(): void
    {
        $tuesday = $this->tuesday();

        $this->fakeOutlook([
            'DTSTART;VALUE=DATE:' . $tuesday->format('Ymd'),
            'DTEND;VALUE=DATE:' . $tuesday->copy()->addDays(2)->format('Ymd'),
            'X-MICROSOFT-CDO-BUSYSTATUS:OOF',
        ]);
        $this->employeeWithOutlook();

        $blocks = $this->blocks();

        $this->assertSame(
            [$tuesday->format('Y-m-d'), $tuesday->copy()->addDay()->format('Y-m-d')],
            array_column($blocks, 'date')
        );
        $this->assertSame(['Hele dag', 'Hele dag'], array_column($blocks, 'time'));
    }

    public function test_beschikbaar_in_outlook_geeft_geen_blok(): void
    {
        $this->fakeOutlook($this->timed('083000', '091500', 'FREE'));
        $this->employeeWithOutlook();

        $this->assertSame([], $this->blocks());
    }

    public function test_blokken_van_twee_medewerkers_staan_er_allebei_in(): void
    {
        $this->fakeOutlook($this->timed('083000', '091500'));
        $first = $this->employeeWithOutlook();
        $second = $this->employeeWithOutlook();

        $this->assertEqualsCanonicalizing([$first->id, $second->id], array_column($this->blocks(), 'employee_id'));
    }

    public function test_medewerker_zonder_koppeling_wordt_niet_bij_outlook_opgevraagd(): void
    {
        Http::fake();
        $this->admin();

        $this->assertSame([], $this->blocks());

        Http::assertNothingSent();
    }

    // ---------- 2. De pagina's ----------

    public function test_admin_ziet_de_bezette_tijd_uit_outlook_in_de_kalender(): void
    {
        $this->fakeOutlook($this->timed('083000', '091500'));
        $employee = $this->employeeWithOutlook();

        $this->actingAs($employee)->get(route('admin.appointments.index'))
            ->assertOk()
            ->assertSee('08:30 - 09:15');
    }

    public function test_adminkalender_heeft_een_legenda_met_alle_kleuren(): void
    {
        $this->actingAs($this->admin())->get(route('admin.appointments.index'))
            ->assertOk()
            ->assertSee('Bevestigd, jij bent erbij')
            ->assertSee('Bevestigd, van een collega')
            ->assertSee('Wacht nog op een reactie')
            ->assertSee('Bezet in Outlook');
    }

    public function test_klant_ziet_de_tijden_uit_outlook_nooit(): void
    {
        $this->fakeOutlook($this->timed('083000', '091500'));
        $this->employeeWithOutlook();

        $client = User::factory()->create(['is_admin' => false]);
        Project::forceCreate(['user_id' => $client->id, 'name' => 'Testproject']);

        $this->actingAs($client)->get(route('client.appointments.index'))
            ->assertOk()
            ->assertDontSee('08:30 - 09:15')
            ->assertDontSee('Outlook');
    }

    public function test_adminkalender_werkt_gewoon_als_outlook_onbereikbaar_is(): void
    {
        Http::fake(['outlook.office365.com/*' => Http::failedConnection()]);
        $employee = $this->employeeWithOutlook();

        $this->actingAs($employee)->get(route('admin.appointments.index'))
            ->assertOk()
            ->assertDontSee('08:30 - 09:15');
    }
}