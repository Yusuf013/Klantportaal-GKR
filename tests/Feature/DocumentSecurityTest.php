<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Test dat het bestand van een projectdocument alleen te openen is door GKR en door de
 * klant van dat project. Voorheen stonden de bestanden op de openbare schijf en was elk
 * bestand zonder inloggen te openen via /storage/documents/...
 */
class DocumentSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = '2026_10_12_090000_move_documents_to_private_disk.php';

    // ---------- Hulpfuncties ----------

    protected function setUp(): void
    {
        parent::setUp();

        // Nep-schijven: tests schrijven nooit echte bestanden
        Storage::fake('local');
        Storage::fake('public');
    }

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
        return Project::forceCreate(['user_id' => $client->id, 'name' => 'Nieuwe website']);
    }

    /**
     * Een document waarvan het bestand echt op de (nep-)schijf staat.
     */
    private function document(Project $project, string $disk = 'local', string $path = 'documents/offerte.pdf', string $name = 'Offerte'): Document
    {
        Storage::disk($disk)->put($path, '%PDF-1.4 testbestand');

        return Document::create(['project_id' => $project->id, 'name' => $name, 'file_path' => $path, 'status' => 'Verzonden']);
    }

    private function runMigration(): void
    {
        (require database_path('migrations/' . self::MIGRATION))->up();
    }

    // ---------- 1. Wie mag het bestand openen ----------

    public function test_gast_wordt_naar_login_gestuurd(): void
    {
        $document = $this->document($this->project($this->client()));

        $this->get(route('documents.file', $document))->assertRedirect(route('login'));
    }

    public function test_klant_van_het_project_kan_het_bestand_openen(): void
    {
        $client = $this->client();
        $document = $this->document($this->project($client));

        $response = $this->actingAs($client)->get(route('documents.file', $document))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        // De browser toont een pdf zelf, onder de naam van het document
        $disposition = (string) $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('inline', $disposition);
        $this->assertStringContainsString('Offerte.pdf', $disposition);
    }

    public function test_een_andere_klant_krijgt_niet_gevonden(): void
    {
        $document = $this->document($this->project($this->client()));

        // 404 en niet 403: zo valt niet te raden welke documenten bestaan
        $this->actingAs($this->client())->get(route('documents.file', $document))->assertNotFound();
    }

    public function test_medewerker_kan_het_bestand_van_elke_klant_openen(): void
    {
        $document = $this->document($this->project($this->client()));

        $this->actingAs($this->admin())->get(route('documents.file', $document))->assertOk();
    }

    public function test_onbekend_document_geeft_niet_gevonden(): void
    {
        $this->actingAs($this->admin())->get('/documents/99999/bestand')->assertNotFound();
    }

    public function test_document_zonder_bestand_op_de_schijf_geeft_niet_gevonden(): void
    {
        $client = $this->client();
        $document = Document::create([
            'project_id' => $this->project($client)->id,
            'name'       => 'Zoekgeraakt',
            'file_path'  => 'documents/bestaat-niet.pdf',
            'status'     => 'Verzonden',
        ]);

        $this->actingAs($client)->get(route('documents.file', $document))->assertNotFound();
    }

    // ---------- 2. Waar het bestand staat ----------

    public function test_upload_van_een_medewerker_komt_op_de_priveschijf(): void
    {
        $project = $this->project($this->client());

        $this->actingAs($this->admin())->post(route('admin.projects.upload', $project), [
            'document_name' => 'Concept ontwerp',
            'file'          => UploadedFile::fake()->create('ontwerp.pdf', 100, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $document = Document::firstOrFail();

        Storage::disk('local')->assertExists($document->file_path);
        Storage::disk('public')->assertMissing($document->file_path);
    }

    public function test_klant_kan_zelf_geen_document_uploaden(): void
    {
        $client = $this->client();
        $project = $this->project($client);

        $this->actingAs($client)->post(route('admin.projects.upload', $project), [
            'document_name' => 'Eigen bestand',
            'file'          => UploadedFile::fake()->create('eigen.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $this->assertDatabaseCount('documents', 0);
    }

    public function test_oud_bestand_op_de_openbare_schijf_werkt_nog_via_de_beveiligde_route(): void
    {
        $client = $this->client();
        $document = $this->document($this->project($client), 'public');

        $this->actingAs($client)->get(route('documents.file', $document))->assertOk();
        // Ook hier geldt: een andere klant komt er niet bij
        $this->actingAs($this->client())->get(route('documents.file', $document))->assertNotFound();
    }

    // ---------- 3. Bestandsnaam en bestandstype ----------

    public function test_ander_bestandstype_wordt_als_download_aangeboden(): void
    {
        // Uploaden kan alleen met pdf en afbeeldingen. Staat er toch iets anders, dan mag de
        // browser dat nooit binnen het portaal uitvoeren: het wordt een download.
        $client = $this->client();
        $document = $this->document($this->project($client), 'local', 'documents/pagina.html', 'Pagina');

        $response = $this->actingAs($client)->get(route('documents.file', $document))->assertOk();

        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_documentnaam_met_vreemde_tekens_geeft_geen_fout(): void
    {
        $client = $this->client();
        $document = $this->document($this->project($client), 'local', 'documents/offerte.pdf', 'Offerte 10/2026: "definitief" voor Café René');

        $response = $this->actingAs($client)->get(route('documents.file', $document))->assertOk();

        $disposition = (string) $response->headers->get('Content-Disposition');
        $this->assertStringNotContainsString('/', $disposition);
        $this->assertStringContainsString('.pdf', $disposition);
    }

    // ---------- 4. De pagina's linken niet meer naar de openbare schijf ----------

    public function test_klantpaginas_linken_naar_de_beveiligde_route(): void
    {
        $client = $this->client();
        $document = $this->document($this->project($client));

        foreach ([route('documents.index'), route('documents.show', $document)] as $page) {
            $this->actingAs($client)->get($page)
                ->assertOk()
                ->assertSee(route('documents.file', $document), false)
                ->assertDontSee('/storage/documents', false);
        }
    }

    public function test_adminpagina_linkt_naar_de_beveiligde_route(): void
    {
        $document = $this->document($this->project($this->client()));

        $this->actingAs($this->admin())->get(route('admin.documents.show', $document))
            ->assertOk()
            ->assertSee(route('documents.file', $document), false)
            ->assertDontSee('/storage/documents', false);
    }

    // ---------- 5. Bestaande bestanden verhuizen ----------

    public function test_bestaand_bestand_verhuist_naar_de_priveschijf(): void
    {
        $client = $this->client();
        $document = $this->document($this->project($client), 'public');

        $this->runMigration();

        Storage::disk('local')->assertExists('documents/offerte.pdf');
        // De oude, openbare link bestaat niet meer
        Storage::disk('public')->assertMissing('documents/offerte.pdf');
        $this->assertSame('%PDF-1.4 testbestand', Storage::disk('local')->get('documents/offerte.pdf'));

        // Het pad in de database is hetzelfde gebleven, en de klant kan er nog bij
        $this->assertSame('documents/offerte.pdf', $document->fresh()->file_path);
        $this->actingAs($client)->get(route('documents.file', $document))->assertOk();
    }

    public function test_verhuizen_kan_veilig_twee_keer(): void
    {
        $this->document($this->project($this->client()), 'public');

        $this->runMigration();
        $this->runMigration();

        Storage::disk('local')->assertExists('documents/offerte.pdf');
        Storage::disk('public')->assertMissing('documents/offerte.pdf');
    }

    public function test_bestand_dat_op_beide_schijven_staat_wordt_alleen_openbaar_verwijderd(): void
    {
        $this->document($this->project($this->client()), 'public');
        Storage::disk('local')->put('documents/offerte.pdf', 'versie op de privéschijf');

        $this->runMigration();

        // De versie op de privéschijf wordt niet overschreven
        $this->assertSame('versie op de privéschijf', Storage::disk('local')->get('documents/offerte.pdf'));
        Storage::disk('public')->assertMissing('documents/offerte.pdf');
    }

    public function test_verhuizen_slaat_ontbrekende_bestanden_en_vreemde_paden_over(): void
    {
        $project = $this->project($this->client());

        // Geen bestand op de schijf
        Document::create(['project_id' => $project->id, 'name' => 'Zoekgeraakt', 'file_path' => 'documents/weg.pdf', 'status' => 'Verzonden']);
        // Een pad dat buiten de map probeert te komen
        Storage::disk('public')->put('geheim.txt', 'niet aankomen');
        Document::create(['project_id' => $project->id, 'name' => 'Vreemd pad', 'file_path' => 'documents/../geheim.txt', 'status' => 'Verzonden']);

        $this->runMigration();

        Storage::disk('public')->assertExists('geheim.txt');
        Storage::disk('local')->assertMissing('geheim.txt');
    }

    public function test_andere_bestanden_op_de_openbare_schijf_blijven_staan(): void
    {
        $this->document($this->project($this->client()), 'public');
        // Een bestand dat niet bij een document hoort
        Storage::disk('public')->put('logo.png', 'logo');

        $this->runMigration();

        Storage::disk('public')->assertExists('logo.png');
    }
}