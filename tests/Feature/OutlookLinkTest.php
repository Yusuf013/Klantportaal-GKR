<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Test het koppelen van een Outlook-agenda aan een medewerker (pagina Instellingen).
 * Er gaat nooit een echt verzoek naar Microsoft: Http::fake() doet alsof Outlook antwoordt.
 */
class OutlookLinkTest extends TestCase
{
    use RefreshDatabase;

    // Verzonnen links. "geheim123" gebruiken we om te controleren dat de link nergens uitlekt.
    private const URL = 'https://outlook.office365.com/owa/calendar/test@example.com/geheim123/calendar.ics';
    private const OTHER_URL = 'https://outlook.office365.com/owa/calendar/test@example.com/anders456/calendar.ics';

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function client(): User
    {
        return User::factory()->create(['is_admin' => false]);
    }

    /**
     * Outlook antwoordt met een geldige (lege) agenda.
     */
    private function fakeOutlook(): void
    {
        Http::fake([
            'outlook.office365.com/*' => Http::response(
                "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:Microsoft Exchange Server 2010\r\nEND:VCALENDAR\r\n",
                200,
                ['Content-Type' => 'text/calendar']
            ),
        ]);
    }

    private function link(User $actor, User $employee, ?string $url)
    {
        return $this->actingAs($actor)
            ->from(route('admin.users.index'))
            ->patch(route('admin.users.outlook-calendar.update', $employee), [
                'outlook_ics_url' => $url,
            ]);
    }

    private function unlink(User $actor, User $employee)
    {
        return $this->actingAs($actor)
            ->from(route('admin.users.index'))
            ->patch(route('admin.users.outlook-calendar.update', $employee), [
                'remove' => '1',
            ]);
    }

    /**
     * Wat er letterlijk in de database staat (dus zonder ontsleutelen).
     */
    private function rawLink(User $user): ?string
    {
        return DB::table('users')->where('id', $user->id)->value('outlook_ics_url');
    }

    public function test_admin_kan_agenda_van_medewerker_koppelen(): void
    {
        $this->fakeOutlook();
        $employee = $this->admin();

        $this->link($this->admin(), $employee, self::URL)
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame(self::URL, $employee->fresh()->outlook_ics_url);
        $this->assertTrue($employee->fresh()->hasOutlookCalendar());
    }

    public function test_link_staat_versleuteld_in_de_database(): void
    {
        $this->fakeOutlook();
        $employee = $this->admin();

        $this->link($this->admin(), $employee, self::URL);

        $raw = $this->rawLink($employee);

        $this->assertNotNull($raw);
        $this->assertStringNotContainsString('geheim123', $raw);
        $this->assertStringNotContainsString('outlook.office365.com', $raw);
    }

    public function test_opgeslagen_link_wordt_nooit_meer_getoond(): void
    {
        $this->fakeOutlook();
        $admin = $this->admin();
        $employee = $this->admin();

        $this->link($admin, $employee, self::URL);

        $this->actingAs($admin)->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Ontkoppelen')
            ->assertDontSee('geheim123');

        // Ook niet als een gebruiker als gegevens naar JavaScript zou gaan
        $this->assertArrayNotHasKey('outlook_ics_url', $employee->fresh()->toArray());
    }

    public function test_klant_kan_geen_agenda_koppelen(): void
    {
        $this->fakeOutlook();
        $employee = $this->admin();

        $this->link($this->client(), $employee, self::URL);

        $this->assertNull($this->rawLink($employee));
    }

    public function test_gast_wordt_naar_login_gestuurd(): void
    {
        $employee = $this->admin();

        $this->patch(route('admin.users.outlook-calendar.update', $employee), [
            'outlook_ics_url' => self::URL,
        ])->assertRedirect(route('login'));

        $this->assertNull($this->rawLink($employee));
    }

    public function test_klant_kan_geen_agenda_krijgen(): void
    {
        $this->fakeOutlook();
        $client = $this->client();

        $this->link($this->admin(), $client, self::URL)->assertNotFound();

        $this->assertNull($this->rawLink($client));
    }

    public function test_link_naar_een_ander_adres_wordt_geweigerd_zonder_iets_op_te_halen(): void
    {
        Http::fake();
        $admin = $this->admin();
        $employee = $this->admin();

        $wrongLinks = [
            'https://evil.example.com/calendar.ics',
            'http://outlook.office365.com/owa/calendar/x/calendar.ics',
            'https://outlook.office365.com@evil.example.com/calendar.ics',
            'geen-link',
        ];

        foreach ($wrongLinks as $wrongLink) {
            $this->link($admin, $employee, $wrongLink)
                ->assertSessionHasErrors(['outlook_ics_url' => 'Dit is geen geldige Outlook-agendalink.']);
        }

        Http::assertNothingSent();
        $this->assertNull($this->rawLink($employee));
    }

    public function test_link_die_geen_agenda_teruggeeft_wordt_geweigerd(): void
    {
        Http::fake(['outlook.office365.com/*' => Http::response('Not found', 404)]);
        $employee = $this->admin();

        $this->link($this->admin(), $employee, self::URL)
            ->assertSessionHasErrors('outlook_ics_url');

        $this->assertNull($this->rawLink($employee));
    }

    public function test_html_koppeling_geeft_een_duidelijke_melding(): void
    {
        Http::fake();
        $employee = $this->admin();

        $this->link($this->admin(), $employee, 'https://outlook.office365.com/owa/calendar/test@example.com/geheim123/calendar.html')
            ->assertSessionHasErrors(['outlook_ics_url' => 'Dit is de HTML-koppeling. Gebruik de ICS-koppeling: die eindigt op .ics.']);

        Http::assertNothingSent();
        $this->assertNull($this->rawLink($employee));
    }

    public function test_na_een_fout_staat_de_link_niet_in_de_sessie_of_de_melding(): void
    {
        Http::fake(['outlook.office365.com/*' => Http::failedConnection()]);
        $employee = $this->admin();

        $this->link($this->admin(), $employee, self::URL)
            ->assertSessionHasErrors(['outlook_ics_url' => 'De Outlook-agenda is op dit moment niet bereikbaar.']);

        // Niets van de link mag in de sessie achterblijven (ook niet als "oude invoer")
        $this->assertStringNotContainsString('geheim123', json_encode(session()->all()));
        $this->assertNull($this->rawLink($employee));
    }

    public function test_leeg_veld_verandert_niets_aan_een_bestaande_koppeling(): void
    {
        $this->fakeOutlook();
        $admin = $this->admin();
        $employee = $this->admin();

        $this->link($admin, $employee, self::URL);
        $this->link($admin, $employee, '')->assertSessionHasErrors('outlook_ics_url');

        $this->assertSame(self::URL, $employee->fresh()->outlook_ics_url);
    }

    public function test_ontkoppelen_verwijdert_de_link(): void
    {
        $this->fakeOutlook();
        $admin = $this->admin();
        $employee = $this->admin();

        $this->link($admin, $employee, self::URL);
        $this->unlink($admin, $employee)->assertSessionHasNoErrors();

        $this->assertNull($this->rawLink($employee));
        $this->assertFalse($employee->fresh()->hasOutlookCalendar());
    }

    public function test_nieuwe_link_vervangt_de_oude(): void
    {
        $this->fakeOutlook();
        $admin = $this->admin();
        $employee = $this->admin();

        $this->link($admin, $employee, self::URL);
        $this->link($admin, $employee, self::OTHER_URL)->assertSessionHasNoErrors();

        $this->assertSame(self::OTHER_URL, $employee->fresh()->outlook_ics_url);
    }

    public function test_koppeling_verdwijnt_als_medewerker_klant_wordt(): void
    {
        $this->fakeOutlook();
        $admin = $this->admin();
        $employee = $this->admin();

        $this->link($admin, $employee, self::URL);
        $this->actingAs($admin)->patch(route('admin.users.toggle-admin', $employee));

        $this->assertNull($this->rawLink($employee));
    }
}