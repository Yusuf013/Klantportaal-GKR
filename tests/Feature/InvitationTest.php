<?php

namespace Tests\Feature;

use App\Http\Controllers\InvitationController;
use App\Mail\ClientInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

/**
 * Test hoe een klant een account krijgt: GKR voegt de klant toe, de klant krijgt een
 * welkomstmail en kiest via de link zelf een wachtwoord.
 * Er wordt niets echt verstuurd: Mail::fake() vangt de mails op.
 */
class InvitationTest extends TestCase
{
    use RefreshDatabase;

    // ---------- Hulpfuncties ----------

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function addClient(string $name = 'Karin Klant', string $email = 'karin@example.com')
    {
        return $this->actingAs($this->admin())->post(route('admin.users.store'), ['name' => $name, 'email' => $email]);
    }

    /**
     * De link uit de welkomstmail die de nieuwe klant kreeg.
     */
    private function linkFromMail(string $email = 'karin@example.com'): string
    {
        return Mail::sent(ClientInvitation::class, fn ($mail) => $mail->hasTo($email))->last()->url;
    }

    /**
     * Een link gebruiken zoals de klant dat doet: zonder ingelogd te zijn.
     */
    private function asGuest(): void
    {
        auth()->logout();
    }

    private function setPassword(string $link, string $password = 'MijnWachtwoord1', ?string $confirmation = null)
    {
        $this->asGuest();

        return $this->post($link, ['password' => $password, 'password_confirmation' => $confirmation ?? $password]);
    }

    // ---------- 1. GKR voegt een klant toe ----------

    public function test_medewerker_voegt_een_klant_toe_en_die_krijgt_een_uitnodiging(): void
    {
        $this->addClient()
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success');

        $client = User::where('email', 'karin@example.com')->firstOrFail();

        $this->assertSame('Karin Klant', $client->name);
        $this->assertFalse((bool) $client->is_admin);
        $this->assertNotNull($client->email_verified_at);

        Mail::assertSent(ClientInvitation::class, fn ($mail) => $mail->hasTo('karin@example.com'));
    }

    public function test_de_mail_bevat_de_link_maar_nooit_een_wachtwoord(): void
    {
        $this->addClient();

        $mail = Mail::sent(ClientInvitation::class)->first();
        $html = $mail->render();

        $this->assertSame('Je account voor het GKR Klantportaal', $mail->envelope()->subject);
        $this->assertStringContainsString('Beste Karin Klant', $html);
        $this->assertStringContainsString('Wachtwoord instellen', $html);
        $this->assertStringContainsString(e($mail->url), $html);
        $this->assertStringNotContainsString('wachtwoord is', strtolower(strip_tags($html)));
    }

    public function test_niemand_kent_het_wachtwoord_van_een_nieuw_account(): void
    {
        $this->addClient();

        $this->asGuest();
        $this->post(route('login'), ['email' => 'karin@example.com', 'password' => 'password']);

        $this->assertGuest();
    }

    public function test_bestaand_e_mailadres_geeft_een_nederlandse_melding(): void
    {
        User::factory()->create(['email' => 'karin@example.com']);

        $this->addClient()->assertSessionHasErrorsIn('invite', ['email' => 'Er bestaat al een account met dit e-mailadres.']);

        $this->assertSame(1, User::where('email', 'karin@example.com')->count());
        Mail::assertNothingSent();
    }

    public function test_e_mailadres_met_hoofdletters_wordt_kleine_letters(): void
    {
        $this->addClient('Karin Klant', '  Karin@Example.COM ')->assertSessionHasNoErrors();

        $this->assertNotNull(User::where('email', 'karin@example.com')->first());
    }

    public function test_naam_en_geldig_e_mailadres_zijn_verplicht(): void
    {
        $this->addClient('', 'geen-adres')->assertSessionHasErrorsIn('invite', ['name', 'email']);

        Mail::assertNothingSent();
    }

    public function test_klant_kan_geen_klanten_toevoegen(): void
    {
        $client = User::factory()->create(['is_admin' => false]);

        $this->actingAs($client)->post(route('admin.users.store'), ['name' => 'Nep', 'email' => 'nep@example.com'])
            ->assertRedirect('/dashboard');

        $this->assertNull(User::where('email', 'nep@example.com')->first());
        Mail::assertNothingSent();
    }

    public function test_gast_kan_geen_klanten_toevoegen(): void
    {
        $this->post(route('admin.users.store'), ['name' => 'Nep', 'email' => 'nep@example.com'])
            ->assertRedirect(route('login'));

        $this->assertNull(User::where('email', 'nep@example.com')->first());
    }

    public function test_mislukte_mail_geeft_een_melding_en_het_account_blijft_bestaan(): void
    {
        Mail::shouldReceive('to')->andThrow(new RuntimeException('Maildienst onbereikbaar'));

        $this->addClient()
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('error');

        $this->assertNotNull(User::where('email', 'karin@example.com')->first());
    }

    // ---------- 2. De klant kiest een wachtwoord ----------

    public function test_de_link_toont_het_formulier(): void
    {
        $this->addClient();
        $link = $this->linkFromMail();

        $this->asGuest();
        $this->get($link)
            ->assertOk()
            ->assertSee('Welkom, Karin Klant')
            ->assertSee('karin@example.com')
            ->assertSee('Wachtwoord instellen');
    }

    public function test_wachtwoord_instellen_logt_de_klant_in(): void
    {
        $this->addClient();
        $link = $this->linkFromMail();

        $this->setPassword($link)
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('success', 'Welkom! Je wachtwoord is ingesteld.');

        $client = User::where('email', 'karin@example.com')->firstOrFail();

        $this->assertAuthenticatedAs($client);
        $this->assertTrue(Hash::check('MijnWachtwoord1', $client->password));
    }

    public function test_daarna_kan_de_klant_gewoon_inloggen(): void
    {
        $this->addClient();
        $this->setPassword($this->linkFromMail());

        $this->asGuest();
        $this->post(route('login'), ['email' => 'karin@example.com', 'password' => 'MijnWachtwoord1']);

        $this->assertAuthenticated();
    }

    public function test_de_link_werkt_maar_een_keer(): void
    {
        $this->addClient();
        $link = $this->linkFromMail();

        $this->setPassword($link, 'EersteWachtwoord1');

        // Iemand die de mail later vindt (en dus niet is ingelogd), kan het wachtwoord niet opnieuw instellen
        $this->asGuest();
        $this->get($link)->assertStatus(410)->assertSee('Deze link werkt niet meer');
        $this->setPassword($link, 'TweedeWachtwoord1')->assertStatus(410);

        $this->assertTrue(Hash::check('EersteWachtwoord1', User::where('email', 'karin@example.com')->first()->password));
    }

    public function test_de_link_verloopt_na_zeven_dagen(): void
    {
        $this->addClient();
        $link = $this->linkFromMail();

        $this->travel(InvitationController::VALID_DAYS)->days();
        $this->travel(1)->minutes();

        $this->asGuest();
        $this->get($link)->assertStatus(410);
    }

    public function test_aangepaste_link_werkt_niet(): void
    {
        $this->addClient();
        $karin = User::where('email', 'karin@example.com')->firstOrFail();
        $other = User::factory()->create(['is_admin' => false]);

        // Iemand verandert het nummer van de klant in de link
        $link = str_replace('/welkom/' . $karin->id . '?', '/welkom/' . $other->id . '?', $this->linkFromMail());

        $this->setPassword($link)->assertStatus(410);
        $this->assertGuest();
    }

    public function test_te_kort_of_niet_gelijk_wachtwoord_geeft_een_nederlandse_melding(): void
    {
        $this->addClient();
        $link = $this->linkFromMail();

        $this->setPassword($link, 'kort')->assertSessionHasErrors(['password' => 'Een wachtwoord moet minimaal 8 tekens lang zijn.']);
        $this->setPassword($link, 'MijnWachtwoord1', 'AnderWachtwoord1')->assertSessionHasErrors(['password' => 'De twee wachtwoorden zijn niet gelijk.']);

        $this->assertGuest();
    }

    public function test_een_medewerker_kan_niet_via_een_uitnodiging_binnenkomen(): void
    {
        $admin = $this->admin();

        $this->asGuest();
        $this->get(InvitationController::urlFor($admin))->assertStatus(410);
    }

    // ---------- 3. Opnieuw uitnodigen ----------

    public function test_medewerker_kan_een_nieuwe_uitnodiging_sturen(): void
    {
        $client = User::factory()->create(['is_admin' => false, 'email' => 'bram@example.com']);

        $this->actingAs($this->admin())->post(route('admin.users.invite', $client))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success');

        Mail::assertSent(ClientInvitation::class, fn ($mail) => $mail->hasTo('bram@example.com'));
    }

    public function test_een_medewerker_kan_niet_opnieuw_worden_uitgenodigd(): void
    {
        $colleague = $this->admin();

        $this->actingAs($this->admin())->post(route('admin.users.invite', $colleague))->assertNotFound();

        Mail::assertNothingSent();
    }

    public function test_gebruikersbeheer_toont_het_formulier_en_de_knop(): void
    {
        User::factory()->create(['is_admin' => false, 'name' => 'Bram Bakker']);

        $this->actingAs($this->admin())->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Klant toevoegen')
            ->assertSee('Toevoegen en uitnodigen')
            ->assertSee('Uitnodiging sturen');
    }
}