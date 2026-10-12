<?php

namespace Tests\Feature;

use App\Mail\NewDocument;
use App\Models\Document;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Test de e-mail aan de klant als GKR een nieuw document of een nieuwe versie klaarzet.
 * Er wordt niets echt verstuurd: Mail::fake() vangt alle mails op.
 */
class DocumentMailTest extends TestCase
{
    use RefreshDatabase;

    // ---------- Hulpfuncties ----------

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Storage::fake('local');
        Storage::fake('public');

        // Mail naar iedereen, zoals online
        config([
            'services.appointments.mail_everyone'       => true,
            'services.appointments.mail_test_recipient' => null,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true, 'name' => 'Stijn Medewerker']);
    }

    private function client(string $email = 'anna@example.com'): User
    {
        return User::factory()->create(['is_admin' => false, 'name' => 'Anna Achternaam', 'email' => $email]);
    }

    private function project(User $client): Project
    {
        return Project::forceCreate(['user_id' => $client->id, 'name' => 'Nieuwe website']);
    }

    private function upload(User $admin, Project $project, string $name = 'Ontwerp homepage')
    {
        return $this->actingAs($admin)->post(route('admin.projects.upload', $project), [
            'document_name' => $name,
            'file'          => UploadedFile::fake()->create('ontwerp.pdf', 100, 'application/pdf'),
        ]);
    }

    private function uploadVersion(User $admin, Document $document, array $extra = [])
    {
        return $this->actingAs($admin)->post(route('admin.documents.versions.store', $document), array_merge([
            'file'        => UploadedFile::fake()->create('ontwerp-v2.pdf', 100, 'application/pdf'),
            'change_note' => 'Logo groter gemaakt',
        ], $extra));
    }

    // ---------- 1. Wanneer gaat er een mail ----------

    public function test_klant_krijgt_mail_bij_een_nieuw_document(): void
    {
        $anna = $this->client();
        $this->upload($this->admin(), $this->project($anna))->assertSessionHasNoErrors();

        Mail::assertSent(NewDocument::class, fn (NewDocument $mail) => $mail->hasTo('anna@example.com'));
        Mail::assertSentCount(1);
    }

    public function test_klant_krijgt_mail_bij_een_nieuwe_versie(): void
    {
        $admin = $this->admin();
        $anna = $this->client();
        $this->upload($admin, $this->project($anna));
        $document = Document::firstOrFail();

        $this->uploadVersion($admin, $document)->assertSessionHasNoErrors();

        Mail::assertSentCount(2);
        Mail::assertSent(NewDocument::class, function (NewDocument $mail) {
            return $mail->hasTo('anna@example.com')
                && $mail->hasSubject('Nieuwe versie van een document in het klantportaal');
        });
    }

    public function test_geen_mail_als_het_uploaden_mislukt(): void
    {
        $admin = $this->admin();
        $anna = $this->client();
        $this->upload($admin, $this->project($anna));
        $document = Document::firstOrFail();
        Mail::assertSentCount(1);

        $this->uploadVersion($admin, $document, ['file' => UploadedFile::fake()->create('pagina.html', 10, 'text/html')])
            ->assertSessionHasErrors(['file'], null, 'version');

        // Geen tweede mail
        Mail::assertSentCount(1);
    }

    public function test_klant_kan_geen_mail_laten_versturen(): void
    {
        $anna = $this->client();
        $this->upload($this->admin(), $this->project($anna));
        $document = Document::firstOrFail();

        $this->uploadVersion($anna, $document)->assertRedirect('/dashboard');

        Mail::assertSentCount(1);
    }

    // ---------- 2. Veiligheidsschakelaar (zoals bij afspraken en chat) ----------

    public function test_zonder_schakelaar_gaat_er_alleen_mail_naar_het_testadres(): void
    {
        config(['services.appointments.mail_everyone' => false]);

        $admin = $this->admin();
        $this->upload($admin, $this->project($this->client('anna@example.com')), 'Voor Anna');
        Mail::assertNothingSent();

        config(['services.appointments.mail_test_recipient' => 'test@example.com']);
        $this->upload($admin, $this->project($this->client('test@example.com')), 'Voor het testadres');

        Mail::assertSentCount(1);
        Mail::assertSent(NewDocument::class, fn (NewDocument $mail) => $mail->hasTo('test@example.com'));
    }

    public function test_mislukte_mail_houdt_het_uploaden_niet_tegen(): void
    {
        // Laat het versturen mislukken, alsof de mailserver onbereikbaar is
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('Mailserver onbereikbaar'));

        $anna = $this->client();
        $this->upload($this->admin(), $this->project($anna))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertDatabaseCount('documents', 1);
        $this->assertDatabaseCount('document_versions', 1);
    }

    // ---------- 3. Wat er in de mail staat ----------

    public function test_mail_bevat_naam_versie_en_link_maar_geen_bestand(): void
    {
        $anna = $this->client();
        $this->upload($this->admin(), $this->project($anna), 'Ontwerp homepage');
        $document = Document::firstOrFail();

        $mail = new NewDocument($anna, $document, 1);

        $mail->assertHasSubject('Nieuw document van GKR in het klantportaal');
        $mail->assertSeeInHtml('Beste Anna Achternaam');
        $mail->assertSeeInHtml('Ontwerp homepage');
        $mail->assertSeeInHtml('Versie 1');
        $mail->assertSeeInHtml(route('documents.show', $document), false);
        // Geen link naar het bestand zelf en geen bijlage
        $mail->assertDontSeeInHtml(route('documents.file', $document), false);
        $this->assertSame([], $mail->attachments);
    }

    public function test_documentnaam_in_de_mail_wordt_nooit_als_code_uitgevoerd(): void
    {
        $anna = $this->client();
        $this->upload($this->admin(), $this->project($anna), '<b>Offerte</b>');
        $document = Document::firstOrFail();

        $mail = new NewDocument($anna, $document, 1);

        $mail->assertDontSeeInHtml('<b>Offerte</b>', false);
        $mail->assertSeeInHtml('&lt;b&gt;Offerte&lt;/b&gt;', false);
    }
}