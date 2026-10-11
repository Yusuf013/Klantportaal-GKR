<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Services\AppointmentAvailability;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Test de melding "link werkt niet" als het portaal een Outlook-agenda niet kan lezen
 * (bijvoorbeeld omdat de link in Outlook is ingetrokken).
 * Er gaat nooit een echt verzoek naar Microsoft: Http::fake() doet alsof Outlook antwoordt.
 */
class OutlookProblemTest extends TestCase
{
    use RefreshDatabase;

    // Verzonnen links. "geheim123" gebruiken we om te controleren dat de link nergens uitlekt.
    private const URL = 'https://outlook.office365.com/owa/calendar/test@example.com/geheim123/calendar.ics';
    private const OTHER_URL = 'https://outlook.office365.com/owa/calendar/test@example.com/anders456/calendar.ics';

    private const EMPTY_CALENDAR = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:Microsoft Exchange Server 2010\r\nEND:VCALENDAR\r\n";

    private const WARNING = 'een Outlook-agenda kan niet gelezen worden';

    // ---------- Hulpfuncties ----------

    protected function setUp(): void
    {
        parent::setUp();

        // Vaste datum en tijd, zodat we precies weten wat er bij "sinds" moet staan
        $this->travelTo(Carbon::parse('2026-10-12 09:15:00', 'Europe/Amsterdam'));
    }

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

    /**
     * Outlook zegt: deze agenda bestaat niet (meer). Dat gebeurt als de link is ingetrokken.
     */
    private function outlookGivesAnError(): void
    {
        Http::fake(['outlook.office365.com/*' => Http::response('', 404)]);
    }

    private function outlookWorks(): void
    {
        Http::fake([
            'outlook.office365.com/*' => Http::response(self::EMPTY_CALENDAR, 200, ['Content-Type' => 'text/calendar']),
        ]);
    }

    private function availability(): AppointmentAvailability
    {
        return app(AppointmentAvailability::class);
    }

    /**
     * Het portaal leest de agenda's, net als bij het openen van de adminkalender.
     */
    private function readCalendars(): void
    {
        $this->availability()->outlookCalendarBlocks();
    }

    // ---------- 1. Onthouden dat het misging ----------

    public function test_mislukt_lezen_wordt_onthouden_met_tijdstip_en_reden(): void
    {
        $this->outlookGivesAnError();
        $employee = $this->employeeWithOutlook();

        $this->readCalendars();

        $this->assertSame([
            $employee->id => [
                'employee' => $employee->name,
                'since'    => '12-10-2026 09:15',
                'reason'   => 'De Outlook-agenda gaf een fout (404).',
            ],
        ], $this->availability()->outlookProblems());
    }

    public function test_als_het_lezen_lukt_is_er_geen_melding(): void
    {
        $this->outlookWorks();
        $this->employeeWithOutlook();

        $this->readCalendars();

        $this->assertSame([], $this->availability()->outlookProblems());
    }

    public function test_medewerker_zonder_koppeling_krijgt_geen_melding(): void
    {
        Http::fake();
        $this->admin();

        $this->readCalendars();

        $this->assertSame([], $this->availability()->outlookProblems());
        Http::assertNothingSent();
    }

    public function test_melding_verdwijnt_zodra_het_lezen_weer_lukt(): void
    {
        // Eerste keer een fout, daarna werkt het weer (bijv. een korte storing bij Microsoft)
        Http::fake([
            'outlook.office365.com/*' => Http::sequence()
                ->push('', 404)
                ->push(self::EMPTY_CALENDAR, 200, ['Content-Type' => 'text/calendar']),
        ]);
        $this->employeeWithOutlook();

        $this->readCalendars();
        $this->assertCount(1, $this->availability()->outlookProblems());

        // Na een mislukte poging probeert het portaal het een minuut lang niet opnieuw
        $this->travel(2)->minutes();
        $this->readCalendars();

        $this->assertSame([], $this->availability()->outlookProblems());
    }

    public function test_sinds_blijft_het_moment_waarop_het_voor_het_eerst_misging(): void
    {
        $this->outlookGivesAnError();
        $employee = $this->employeeWithOutlook();

        $this->readCalendars();
        $this->travel(3)->hours();
        $this->readCalendars();

        $this->assertSame('12-10-2026 09:15', $this->availability()->outlookProblems()[$employee->id]['since']);
    }

    public function test_een_nieuwe_link_begint_zonder_melding(): void
    {
        $this->outlookGivesAnError();
        $employee = $this->employeeWithOutlook();

        $this->readCalendars();
        $this->assertCount(1, $this->availability()->outlookProblems());

        // De admin plakt een nieuwe link: de oude melding hoort daar niet bij
        $employee->outlook_ics_url = self::OTHER_URL;
        $employee->save();

        $this->assertSame([], $this->availability()->outlookProblems());
    }

    public function test_onleesbare_opgeslagen_link_geeft_een_melding_in_gewone_taal(): void
    {
        Http::fake();
        $employee = $this->admin();
        // Rechtstreeks in de database gezet, dus niet geldig versleuteld
        // (dit gebeurt in het echt als APP_KEY verandert)
        DB::table('users')->where('id', $employee->id)->update(['outlook_ics_url' => 'niet-te-ontsleutelen']);

        $this->readCalendars();

        $problems = $this->availability()->outlookProblems();

        $this->assertArrayHasKey($employee->id, $problems);
        // Niet de technische Engelse melding van Laravel, maar onze eigen uitleg
        $this->assertSame('De opgeslagen link is niet meer te lezen. Plak de link opnieuw.', $problems[$employee->id]['reason']);
        Http::assertNothingSent();
    }

    public function test_onleesbare_link_staat_met_uitleg_bij_gebruikersbeheer(): void
    {
        Http::fake();
        $employee = $this->admin();
        DB::table('users')->where('id', $employee->id)->update(['outlook_ics_url' => 'niet-te-ontsleutelen']);
        $this->readCalendars();

        $this->actingAs($this->admin())->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Link werkt niet')
            ->assertSee('De opgeslagen link is niet meer te lezen. Plak de link opnieuw.');
    }

    // ---------- 2. De pagina's ----------

    public function test_admin_ziet_de_waarschuwing_boven_de_kalender(): void
    {
        $this->outlookGivesAnError();
        $this->employeeWithOutlook();

        $this->actingAs($this->admin())->get(route('admin.appointments.index'))
            ->assertOk()
            ->assertSee(self::WARNING)
            ->assertSee('lukt niet sinds 12-10-2026 09:15');
    }

    public function test_geen_waarschuwing_boven_de_kalender_als_alles_werkt(): void
    {
        $this->outlookWorks();
        $this->employeeWithOutlook();

        $this->actingAs($this->admin())->get(route('admin.appointments.index'))
            ->assertOk()
            ->assertDontSee(self::WARNING);
    }

    public function test_gebruikersbeheer_toont_link_werkt_niet_met_de_reden(): void
    {
        $this->outlookGivesAnError();
        $this->employeeWithOutlook();
        $this->readCalendars();

        $this->actingAs($this->admin())->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Link werkt niet')
            ->assertSee('lukt niet sinds 12-10-2026 09:15')
            ->assertSee('De Outlook-agenda gaf een fout (404).');
    }

    public function test_gebruikersbeheer_toont_gekoppeld_als_alles_werkt(): void
    {
        $this->outlookWorks();
        $this->employeeWithOutlook();
        $this->readCalendars();

        $this->actingAs($this->admin())->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Gekoppeld')
            ->assertDontSee('Link werkt niet');
    }

    // ---------- 3. Veiligheid ----------

    public function test_de_link_staat_nergens_in_de_melding(): void
    {
        $this->outlookGivesAnError();
        $this->employeeWithOutlook();
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.appointments.index'))->assertOk()->assertDontSee('geheim123');
        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk()->assertDontSee('geheim123');

        $this->assertStringNotContainsString('geheim123', json_encode($this->availability()->outlookProblems()));
    }

    public function test_klant_ziet_de_waarschuwing_niet(): void
    {
        $this->outlookGivesAnError();
        $this->employeeWithOutlook();
        $this->readCalendars();

        $client = User::factory()->create(['is_admin' => false]);
        Project::forceCreate(['user_id' => $client->id, 'name' => 'Testproject']);

        $this->actingAs($client)->get(route('client.appointments.index'))
            ->assertOk()
            ->assertDontSee(self::WARNING)
            ->assertDontSee('Outlook');
    }
}