<?php

namespace Tests\Feature;

use App\Mail\NewChatMessage;
use App\Models\Message;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * Test de e-mailmelding bij een nieuw chatbericht. De regels:
 * 1. één mail per stapel ongelezen berichten;
 * 2. geen mail als de ontvanger op dat moment in het portaal zit;
 * 3. de tekst van het bericht staat nooit in de mail.
 *
 * Er wordt niets echt verstuurd: Mail::fake() vangt alle mails op.
 */
class ChatMailTest extends TestCase
{
    use RefreshDatabase;

    private const GKR_ADDRESS = 'info@example.com';

    // ---------- Hulpfuncties ----------

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Cache::flush();
        Storage::fake('local');
        Storage::fake('public');

        $this->travelTo(Carbon::parse('2026-10-12 12:00:00', 'Europe/Amsterdam'));

        config([
            // In deze tests mag iedereen mail krijgen, behalve waar we de schakelaar zelf testen
            'services.appointments.mail_everyone'       => true,
            'services.appointments.mail_test_recipient' => null,
            'services.chat.gkr_address'                 => self::GKR_ADDRESS,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true, 'name' => 'Stijn Medewerker', 'email' => 'stijn@example.com']);
    }

    private function client(string $name = 'Karin Klant', string $email = 'karin@example.com'): User
    {
        return User::factory()->create(['is_admin' => false, 'name' => $name, 'email' => $email]);
    }

    private function clientSends(User $client, string $body = 'Vraag van de klant')
    {
        return $this->actingAs($client)->post(route('chat.store'), ['body' => $body]);
    }

    private function adminReplies(User $admin, User $client, string $body = 'Antwoord van GKR')
    {
        return $this->actingAs($admin)->post(route('chat.store'), ['receiver_id' => $client->id, 'body' => $body]);
    }

    /**
     * Een pagina van het portaal staat bij deze gebruiker open (de teller wordt opgevraagd).
     */
    private function isInThePortal(User $user): void
    {
        $this->actingAs($user)->getJson(route('chat.unread'))->assertOk();
    }

    /**
     * Mails voor het gedeelde adres van GKR.
     */
    private function assertGkrMails(int $count): void
    {
        $this->assertCount($count, Mail::sent(NewChatMessage::class, fn ($mail) => $mail->hasTo(self::GKR_ADDRESS)));
    }

    private function assertMailsTo(string $address, int $count): void
    {
        $this->assertCount($count, Mail::sent(NewChatMessage::class, fn ($mail) => $mail->hasTo($address)));
    }

    // ---------- 1. Klant schrijft: melding naar GKR ----------

    public function test_klant_schrijft_en_gkr_krijgt_een_mail_op_het_gedeelde_adres(): void
    {
        $this->admin();
        $karin = $this->client();

        $this->clientSends($karin)->assertSessionHasNoErrors();

        Mail::assertSent(NewChatMessage::class, 1);
        Mail::assertSent(NewChatMessage::class, function ($mail) {
            return $mail->hasTo(self::GKR_ADDRESS)
                && $mail->envelope()->subject === 'Nieuw bericht van Karin Klant in het klantportaal';
        });

        // Niet naar de medewerker persoonlijk
        $this->assertMailsTo('stijn@example.com', 0);
    }

    public function test_meer_berichten_terwijl_er_nog_niet_gelezen_is_geven_geen_extra_mail(): void
    {
        $karin = $this->client();

        $this->clientSends($karin, 'Eerste bericht');
        $this->travel(10)->minutes();
        $this->clientSends($karin, 'Tweede bericht');
        $this->travel(3)->hours();
        $this->clientSends($karin, 'Derde bericht');

        $this->assertGkrMails(1);
        $this->assertDatabaseCount('messages', 3);
    }

    public function test_na_het_lezen_geeft_een_volgend_bericht_weer_een_mail(): void
    {
        $admin = $this->admin();
        $karin = $this->client();

        $this->clientSends($karin, 'Eerste bericht');
        $this->assertGkrMails(1);

        // Een medewerker opent het gesprek en leest het bericht
        $this->actingAs($admin)->get(route('chat.index', ['client_id' => $karin->id]))->assertOk();

        // Later, als niemand van GKR meer in het portaal zit, schrijft de klant opnieuw
        $this->travel(1)->hours();
        $this->clientSends($karin, 'Nieuwe vraag');

        $this->assertGkrMails(2);
    }

    public function test_lezen_via_de_chat_zonder_herladen_telt_ook(): void
    {
        $admin = $this->admin();
        $karin = $this->client();

        $this->clientSends($karin, 'Eerste bericht');

        // De chatpagina van de medewerker haalt het bericht op terwijl het gesprek in beeld is
        $this->actingAs($admin)->getJson(route('chat.messages', ['client_id' => $karin->id, 'seen' => 1]))->assertOk();

        $this->travel(1)->hours();
        $this->clientSends($karin, 'Nieuwe vraag');

        $this->assertGkrMails(2);
    }

    public function test_geen_mail_als_een_medewerker_op_dat_moment_in_het_portaal_zit(): void
    {
        $admin = $this->admin();
        $karin = $this->client();

        // De medewerker heeft het portaal open en ziet de rode teller verschijnen
        $this->isInThePortal($admin);
        $this->clientSends($karin);

        Mail::assertNothingSent();
    }

    public function test_medewerker_gaat_weg_zonder_te_lezen_dan_komt_de_mail_bij_het_volgende_bericht(): void
    {
        $admin = $this->admin();
        $karin = $this->client();

        $this->isInThePortal($admin);
        $this->clientSends($karin, 'Eerste bericht');
        Mail::assertNothingSent();

        // De medewerker sluit het portaal zonder het gesprek te openen
        $this->travel(5)->minutes();
        $this->clientSends($karin, 'Tweede bericht');

        $this->assertGkrMails(1);
    }

    public function test_aanwezigheid_duurt_maar_kort(): void
    {
        $admin = $this->admin();
        $karin = $this->client();

        $this->isInThePortal($admin);

        // Na ruim een minuut zonder teken van leven telt de medewerker niet meer als aanwezig
        $this->travel(61)->seconds();
        $this->clientSends($karin);

        $this->assertGkrMails(1);
    }

    // ---------- 2. GKR antwoordt: melding naar de klant ----------

    public function test_gkr_antwoordt_en_de_klant_krijgt_een_mail_op_het_eigen_adres(): void
    {
        $admin = $this->admin();
        $karin = $this->client();

        $this->adminReplies($admin, $karin)->assertSessionHasNoErrors();

        Mail::assertSent(NewChatMessage::class, 1);
        Mail::assertSent(NewChatMessage::class, function ($mail) {
            return $mail->hasTo('karin@example.com')
                && $mail->envelope()->subject === 'Nieuw bericht van GKR in het klantportaal';
        });

        $this->assertGkrMails(0);
    }

    public function test_geen_mail_als_de_klant_op_dat_moment_in_het_portaal_zit(): void
    {
        $admin = $this->admin();
        $karin = $this->client();

        $this->isInThePortal($karin);
        $this->adminReplies($admin, $karin);

        Mail::assertNothingSent();
    }

    public function test_een_andere_klant_in_het_portaal_houdt_de_mail_niet_tegen(): void
    {
        $admin = $this->admin();
        $karin = $this->client();
        $bram = $this->client('Bram Bakker', 'bram@example.com');

        // Bram zit in het portaal, Karin niet
        $this->isInThePortal($bram);
        $this->adminReplies($admin, $karin);

        $this->assertMailsTo('karin@example.com', 1);
        $this->assertMailsTo('bram@example.com', 0);
    }

    public function test_elke_klant_heeft_een_eigen_stapel(): void
    {
        $admin = $this->admin();
        $karin = $this->client();
        $bram = $this->client('Bram Bakker', 'bram@example.com');

        $this->adminReplies($admin, $karin, 'Bericht voor Karin');
        $this->adminReplies($admin, $karin, 'Nog een bericht voor Karin');
        $this->adminReplies($admin, $bram, 'Bericht voor Bram');

        $this->assertMailsTo('karin@example.com', 1);
        $this->assertMailsTo('bram@example.com', 1);
    }

    public function test_een_live_gesprek_geeft_geen_stroom_aan_mails(): void
    {
        $admin = $this->admin();
        $karin = $this->client();

        // De klant schrijft terwijl niemand van GKR er is: één mail naar GKR
        $this->clientSends($karin, 'Zijn jullie er?');

        // Daarna zitten beide in de chat en schrijven om en om.
        // De chatpagina meldt zich elke paar seconden en leest mee.
        for ($i = 1; $i <= 3; $i++) {
            $this->actingAs($admin)->getJson(route('chat.messages', ['client_id' => $karin->id, 'seen' => 1]));
            $this->actingAs($karin)->getJson(route('chat.messages', ['seen' => 1]));

            $this->adminReplies($admin, $karin, "Antwoord {$i}");
            $this->clientSends($karin, "Vraag {$i}");

            $this->travel(20)->seconds();
        }

        Mail::assertSent(NewChatMessage::class, 1);
    }

    // ---------- 3. De inhoud van de mail ----------

    public function test_de_tekst_van_het_bericht_staat_nooit_in_de_mail(): void
    {
        $admin = $this->admin();
        $karin = $this->client();

        $this->adminReplies($admin, $karin, 'Vertrouwelijke tekst over de offerte');

        $html = Mail::sent(NewChatMessage::class)->first()->render();

        $this->assertStringNotContainsString('Vertrouwelijke tekst', $html);
        $this->assertStringContainsString('Beste Karin Klant', $html);
        $this->assertStringContainsString('Open de chat', $html);
        // De knop gaat naar de chat van de klant zelf, zonder nummer in het adres
        $this->assertStringContainsString('href="' . route('chat.index') . '"', $html);
    }

    public function test_mail_voor_gkr_noemt_de_klant_en_linkt_naar_dat_gesprek(): void
    {
        $karin = $this->client();

        $this->clientSends($karin, 'Vertrouwelijke vraag');

        $html = Mail::sent(NewChatMessage::class)->first()->render();

        $this->assertStringNotContainsString('Vertrouwelijke vraag', $html);
        $this->assertStringContainsString('Nieuw bericht van Karin Klant', $html);
        $this->assertStringContainsString('client_id=' . $karin->id, $html);
    }

    public function test_naam_met_code_wordt_onschadelijk_gemaakt_in_de_mail(): void
    {
        $client = $this->client('<script>alert(1)</script>', 'vreemd@example.com');

        $this->clientSends($client);

        $html = Mail::sent(NewChatMessage::class)->first()->render();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    // ---------- 4. Veiligheidsschakelaar en instellingen ----------

    public function test_zolang_de_schakelaar_uit_staat_gaat_er_alleen_mail_naar_het_testadres(): void
    {
        config([
            'services.appointments.mail_everyone'       => false,
            'services.appointments.mail_test_recipient' => 'karin@example.com',
        ]);

        $admin = $this->admin();
        $karin = $this->client();
        $bram = $this->client('Bram Bakker', 'bram@example.com');

        $this->adminReplies($admin, $karin); // Karin is het testadres: wel een mail
        $this->adminReplies($admin, $bram);  // Bram niet
        $this->clientSends($bram);           // het adres van GKR is ook niet het testadres

        Mail::assertSent(NewChatMessage::class, 1);
        $this->assertMailsTo('karin@example.com', 1);
    }

    public function test_schakelaar_uit_en_geen_testadres_betekent_geen_enkele_mail(): void
    {
        config([
            'services.appointments.mail_everyone'       => false,
            'services.appointments.mail_test_recipient' => null,
        ]);

        $admin = $this->admin();
        $karin = $this->client();

        $this->adminReplies($admin, $karin);
        $this->clientSends($karin);

        Mail::assertNothingSent();
    }

    public function test_zonder_adres_voor_gkr_gaat_er_geen_mail_naar_gkr(): void
    {
        config(['services.chat.gkr_address' => null]);

        $karin = $this->client();

        $this->clientSends($karin)->assertSessionHasNoErrors();

        Mail::assertNothingSent();
        $this->assertDatabaseCount('messages', 1);
    }

    public function test_ongeldig_adres_voor_gkr_wordt_genegeerd(): void
    {
        config(['services.chat.gkr_address' => 'geen-emailadres']);

        $this->clientSends($this->client())->assertSessionHasNoErrors();

        Mail::assertNothingSent();
    }

    // ---------- 5. Robuustheid ----------

    public function test_meerdere_bijlagen_tegelijk_geven_een_mail(): void
    {
        $karin = $this->client();

        $this->actingAs($karin)->post(route('chat.store'), [
            'body'  => 'Zie de bijlagen',
            'files' => [
                UploadedFile::fake()->create('offerte.pdf', 20, 'application/pdf'),
                UploadedFile::fake()->create('planning.pdf', 20, 'application/pdf'),
                UploadedFile::fake()->create('logo.png', 20, 'image/png'),
            ],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('messages', 3);
        $this->assertGkrMails(1);
    }

    public function test_mislukt_bericht_geeft_geen_mail(): void
    {
        // Leeg bericht: wordt geweigerd, dus er valt niets te melden
        $this->clientSends($this->client(), '')->assertSessionHasErrors();

        Mail::assertNothingSent();
    }

    public function test_mislukte_mail_houdt_het_chatbericht_niet_tegen(): void
    {
        // De maildienst is onbereikbaar
        Mail::shouldReceive('to')->andThrow(new RuntimeException('Maildienst onbereikbaar'));

        $karin = $this->client();

        $this->clientSends($karin, 'Dit bericht moet gewoon aankomen')->assertSessionHasNoErrors();

        $this->assertSame('Dit bericht moet gewoon aankomen', Message::firstOrFail()->body);
    }

    public function test_na_een_mislukte_mail_wordt_het_bij_het_volgende_bericht_opnieuw_geprobeerd(): void
    {
        $karin = $this->client();

        // Een nagemaakte maildienst: de eerste poging mislukt, daarna werkt hij weer
        $attempts = 0;
        $sent = 0;

        $pending = Mockery::mock();
        $pending->shouldReceive('send')->andReturnUsing(function () use (&$sent) {
            $sent++;
        });

        Mail::shouldReceive('to')->andReturnUsing(function () use (&$attempts, $pending) {
            if (++$attempts === 1) {
                throw new RuntimeException('Maildienst onbereikbaar');
            }

            return $pending;
        });

        $this->clientSends($karin, 'Eerste bericht');   // poging 1: mislukt
        $this->clientSends($karin, 'Tweede bericht');   // poging 2: gelukt
        $this->clientSends($karin, 'Derde bericht');    // al gemeld: geen nieuwe poging

        $this->assertSame(2, $attempts);
        $this->assertSame(1, $sent);
        $this->assertDatabaseCount('messages', 3);
    }
}