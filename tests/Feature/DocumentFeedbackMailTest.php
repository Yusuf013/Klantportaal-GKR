<?php

namespace Tests\Feature;

use App\Mail\DocumentFeedbackForGkr;
use App\Models\Document;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Test de e-mail aan GKR als een klant reageert op een document of akkoord geeft.
 * Er wordt niets echt verstuurd: Mail::fake() vangt alle mails op.
 */
class DocumentFeedbackMailTest extends TestCase
{
    use RefreshDatabase;

    private const GKR = 'info@gkr.example';

    // ---------- Hulpfuncties ----------

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');

        // Mail naar iedereen, zoals online, en een gedeeld adres van GKR
        config([
            'services.appointments.mail_everyone'       => true,
            'services.appointments.mail_test_recipient' => null,
            'services.chat.gkr_address'                 => self::GKR,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true, 'name' => 'Stijn Medewerker']);
    }

    private function client(string $name = 'Anna Achternaam'): User
    {
        return User::factory()->create(['is_admin' => false, 'name' => $name]);
    }

    /**
     * Een document zoals GKR het uploadt (met versie 1). Pas daarna vangen we mails op,
     * zodat de mail aan de klant over het nieuwe document hier niet meetelt.
     */
    private function document(User $client, User $admin, string $name = 'Ontwerp homepage'): Document
    {
        $project = Project::forceCreate(['user_id' => $client->id, 'name' => 'Nieuwe website']);

        $this->actingAs($admin)->post(route('admin.projects.upload', $project), [
            'document_name' => $name,
            'file'          => UploadedFile::fake()->create('ontwerp.pdf', 100, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        Mail::fake();

        return Document::where('name', $name)->firstOrFail();
    }

    private function comment(User $user, Document $document, string $text = 'Logo mag groter')
    {
        return $this->actingAs($user)->post(route('comments.store', $document), ['content' => $text]);
    }

    private function approve(User $client, Document $document)
    {
        return $this->actingAs($client)->post(route('documents.approve', $document), [
            'version_id' => $document->currentVersion()->value('document_versions.id'),
        ]);
    }

    // ---------- 1. Reacties ----------

    public function test_gkr_krijgt_mail_als_de_klant_reageert(): void
    {
        $anna = $this->client();
        $document = $this->document($anna, $this->admin());

        $this->comment($anna, $document)->assertSessionHas('success');

        Mail::assertSentCount(1);
        Mail::assertSent(DocumentFeedbackForGkr::class, function (DocumentFeedbackForGkr $mail) {
            return $mail->hasTo(self::GKR)
                && $mail->hasSubject('Nieuwe reactie van Anna Achternaam op een document');
        });
    }

    public function test_meerdere_reacties_achter_elkaar_geven_maar_een_mail(): void
    {
        $anna = $this->client();
        $document = $this->document($anna, $this->admin());

        $this->comment($anna, $document, 'Eerste reactie');
        $this->comment($anna, $document, 'Tweede reactie');
        $this->comment($anna, $document, 'Derde reactie');

        Mail::assertSentCount(1);
    }

    public function test_na_een_reactie_van_gkr_mag_er_weer_een_mail_komen(): void
    {
        $admin = $this->admin();
        $anna = $this->client();
        $document = $this->document($anna, $admin);

        $this->comment($anna, $document, 'Vraag van Anna');
        $this->comment($admin, $document, 'Antwoord van GKR');
        $this->comment($anna, $document, 'Nieuwe vraag van Anna');

        Mail::assertSentCount(2);
    }

    public function test_reactie_van_gkr_zelf_geeft_geen_mail(): void
    {
        $admin = $this->admin();
        $document = $this->document($this->client(), $admin);

        $this->comment($admin, $document, 'Toelichting van GKR');

        Mail::assertNothingSent();
    }

    public function test_per_document_apart(): void
    {
        $admin = $this->admin();
        $anna = $this->client();
        $first = $this->document($anna, $admin, 'Ontwerp homepage');
        $second = $this->document($anna, $admin, 'Offerte');

        $this->comment($anna, $first);
        $this->comment($anna, $second);

        Mail::assertSentCount(2);
    }

    // ---------- 2. Akkoord ----------

    public function test_gkr_krijgt_mail_bij_een_akkoord_met_het_versienummer(): void
    {
        $anna = $this->client();
        $document = $this->document($anna, $this->admin());

        $this->approve($anna, $document)->assertSessionHas('success');

        Mail::assertSentCount(1);
        Mail::assertSent(DocumentFeedbackForGkr::class, function (DocumentFeedbackForGkr $mail) {
            return $mail->hasTo(self::GKR)
                && $mail->hasSubject('Akkoord van Anna Achternaam op een document');
        });
    }

    public function test_geweigerd_akkoord_geeft_geen_mail(): void
    {
        $admin = $this->admin();
        $anna = $this->client();
        $document = $this->document($anna, $admin);
        $oldVersionId = $document->currentVersion()->value('document_versions.id');

        // Intussen een nieuwe versie (die mail aan de klant tellen we niet mee)
        $this->actingAs($admin)->post(route('admin.documents.versions.store', $document), [
            'file' => UploadedFile::fake()->create('v2.pdf', 100, 'application/pdf'),
        ]);
        Mail::fake();

        $this->actingAs($anna)->post(route('documents.approve', $document), ['version_id' => $oldVersionId])
            ->assertSessionHas('error');

        Mail::assertNothingSent();
    }

    public function test_andere_klant_kan_geen_mail_laten_versturen(): void
    {
        $document = $this->document($this->client('Anna Achternaam'), $this->admin());
        $bram = $this->client('Bram Bakker');

        $this->comment($bram, $document)->assertNotFound();
        $this->approve($bram, $document)->assertNotFound();

        Mail::assertNothingSent();
    }

    // ---------- 3. Instellingen en fouten ----------

    public function test_zonder_adres_van_gkr_gaat_er_niets(): void
    {
        config(['services.chat.gkr_address' => null]);

        $anna = $this->client();
        $document = $this->document($anna, $this->admin());

        $this->comment($anna, $document);
        $this->approve($anna, $document);

        Mail::assertNothingSent();
    }

    public function test_zonder_schakelaar_alleen_naar_het_testadres(): void
    {
        config(['services.appointments.mail_everyone' => false]);

        $anna = $this->client();
        $document = $this->document($anna, $this->admin());

        $this->comment($anna, $document);
        Mail::assertNothingSent();

        config(['services.appointments.mail_test_recipient' => self::GKR]);
        $this->approve($anna, $document);
        Mail::assertSentCount(1);
    }

    public function test_mislukte_mail_houdt_reactie_en_akkoord_niet_tegen(): void
    {
        $anna = $this->client();
        $document = $this->document($anna, $this->admin());

        // Laat het versturen mislukken, alsof de mailserver onbereikbaar is
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('Mailserver onbereikbaar'));

        $this->comment($anna, $document)->assertSessionHas('success');
        $this->approve($anna, $document)->assertSessionHas('success');

        $this->assertDatabaseCount('comments', 1);
        $this->assertSame('Akkoord', $document->fresh()->status);
    }

    // ---------- 4. Wat er in de mail staat ----------

    public function test_mail_bevat_naam_en_link_maar_nooit_de_tekst_van_de_reactie(): void
    {
        $anna = $this->client();
        $document = $this->document($anna, $this->admin());

        $comment = new DocumentFeedbackForGkr($anna, $document, DocumentFeedbackForGkr::COMMENT, null);
        $comment->assertSeeInHtml('Nieuwe reactie van Anna Achternaam');
        $comment->assertSeeInHtml('Ontwerp homepage');
        $comment->assertSeeInHtml(route('admin.documents.show', $document), false);

        $approved = new DocumentFeedbackForGkr($anna, $document, DocumentFeedbackForGkr::APPROVED, 2);
        $approved->assertSeeInHtml('Anna Achternaam heeft akkoord gegeven');
        $approved->assertSeeInHtml('Versie 2');

        // De reactie zelf wordt nooit aan de mail meegegeven: controleer met een echte reactie
        $this->comment($anna, $document, 'GEHEIME-REACTIETEKST');
        Mail::assertSent(DocumentFeedbackForGkr::class, function (DocumentFeedbackForGkr $mail) {
            return ! str_contains($mail->render(), 'GEHEIME-REACTIETEKST');
        });
    }
}