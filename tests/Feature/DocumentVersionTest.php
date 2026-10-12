<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Test het versiebeheer van projectdocumenten:
 * - GKR uploadt een nieuwe versie, de oude blijft bewaard;
 * - de klant keurt altijd de nieuwste versie goed, en het akkoord hoort bij die versie;
 * - elke versie is alleen te openen door GKR en de klant van het project;
 * - reacties onthouden bij welke versie ze zijn geplaatst.
 */
class DocumentVersionTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = '2026_10_13_100000_create_document_versions_table.php';

    // ---------- Hulpfuncties ----------

    protected function setUp(): void
    {
        parent::setUp();

        // Nep-schijven: tests schrijven nooit echte bestanden
        Storage::fake('local');
        Storage::fake('public');
    }

    private function admin(string $name = 'Stijn Medewerker'): User
    {
        return User::factory()->create(['is_admin' => true, 'name' => $name]);
    }

    private function client(string $name = 'Anna Achternaam'): User
    {
        return User::factory()->create(['is_admin' => false, 'name' => $name]);
    }

    private function project(User $client): Project
    {
        return Project::forceCreate(['user_id' => $client->id, 'name' => 'Nieuwe website']);
    }

    /**
     * Een document zoals GKR het uploadt: via het echte uploadformulier (dus met versie 1).
     */
    private function uploadedDocument(Project $project, User $admin, string $name = 'Ontwerp homepage'): Document
    {
        $this->actingAs($admin)->post(route('admin.projects.upload', $project), [
            'document_name' => $name,
            'file'          => UploadedFile::fake()->create('ontwerp-v1.pdf', 100, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        return Document::where('name', $name)->firstOrFail();
    }

    /**
     * Een document van vóór het versiebeheer: zonder versies.
     */
    private function oldDocument(Project $project, string $status = 'Verzonden'): Document
    {
        Storage::disk('local')->put('documents/oud.pdf', '%PDF oude versie');

        return Document::create([
            'project_id'  => $project->id,
            'name'        => 'Offerte',
            'file_path'   => 'documents/oud.pdf',
            'status'      => $status,
            'approved_at' => $status === 'Akkoord' ? now()->subDay() : null,
        ]);
    }

    private function uploadVersion(User $admin, Document $document, array $extra = [])
    {
        return $this->actingAs($admin)->post(route('admin.documents.versions.store', $document), array_merge([
            'file'        => UploadedFile::fake()->create('ontwerp-nieuw.pdf', 100, 'application/pdf'),
            'change_note' => 'Logo groter gemaakt',
        ], $extra));
    }

    // ---------- 1. Versie 1 bij de eerste upload ----------

    public function test_eerste_upload_wordt_versie_1(): void
    {
        $admin = $this->admin();
        $document = $this->uploadedDocument($this->project($this->client()), $admin);

        $version = $document->versions()->firstOrFail();

        $this->assertSame(1, $version->version_number);
        $this->assertSame($document->file_path, $version->file_path);
        $this->assertSame($admin->id, (int) $version->uploaded_by);
        $this->assertSame(1, $document->versions()->count());
    }

    // ---------- 2. Een nieuwe versie uploaden ----------

    public function test_medewerker_uploadt_een_nieuwe_versie(): void
    {
        $admin = $this->admin();
        $document = $this->uploadedDocument($this->project($this->client()), $admin);
        $oldPath = $document->file_path;

        $this->uploadVersion($admin, $document)
            ->assertRedirect(route('admin.documents.show', $document))
            ->assertSessionHas('success');

        $document->refresh();
        $version = $document->currentVersion()->firstOrFail();

        $this->assertSame(2, $version->version_number);
        $this->assertSame('Logo groter gemaakt', $version->change_note);
        $this->assertSame($admin->id, (int) $version->uploaded_by);

        // Het document wijst naar het nieuwe bestand
        $this->assertSame($version->file_path, $document->file_path);
        $this->assertNotSame($oldPath, $document->file_path);

        // Het oude bestand blijft bewaard, het nieuwe staat op de privéschijf
        Storage::disk('local')->assertExists($oldPath);
        Storage::disk('local')->assertExists($version->file_path);
        Storage::disk('public')->assertMissing($version->file_path);
    }

    public function test_nieuwe_versie_zet_een_goedgekeurd_document_terug_op_verzonden(): void
    {
        $admin = $this->admin();
        $anna = $this->client();
        $document = $this->uploadedDocument($this->project($anna), $admin);

        $this->actingAs($anna)->post(route('documents.approve', $document), [
            'version_id' => $document->currentVersion()->value('document_versions.id'),
        ]);
        $this->assertSame('Akkoord', $document->fresh()->status);

        $this->uploadVersion($admin, $document);

        $document->refresh();
        $this->assertSame('Verzonden', $document->status);
        $this->assertNull($document->approved_at);

        // Het akkoord op versie 1 blijft zwart op wit bewaard
        $first = $document->versions()->where('version_number', 1)->firstOrFail();
        $this->assertNotNull($first->approved_at);
        $this->assertSame($anna->id, (int) $first->approved_by);
    }

    public function test_versienummers_lopen_op(): void
    {
        $admin = $this->admin();
        $document = $this->uploadedDocument($this->project($this->client()), $admin);

        $this->uploadVersion($admin, $document);
        $this->uploadVersion($admin, $document);

        $this->assertSame([3, 2, 1], $document->versions()->pluck('version_number')->map(fn ($n) => (int) $n)->all());
    }

    public function test_document_van_voor_het_versiebeheer_krijgt_eerst_versie_1(): void
    {
        $admin = $this->admin();
        $document = $this->oldDocument($this->project($this->client()), 'Akkoord');

        $this->uploadVersion($admin, $document);

        $versions = $document->versions()->get();
        $this->assertCount(2, $versions);

        $first = $versions->firstWhere('version_number', 1);
        $this->assertSame('documents/oud.pdf', $first->file_path);
        // Het oude akkoord gaat niet verloren
        $this->assertNotNull($first->approved_at);
        $this->assertSame(2, $document->currentVersion()->firstOrFail()->version_number);
    }

    public function test_toelichting_is_optioneel(): void
    {
        $admin = $this->admin();
        $document = $this->uploadedDocument($this->project($this->client()), $admin);

        $this->uploadVersion($admin, $document, ['change_note' => ''])->assertSessionHasNoErrors();

        $this->assertNull($document->currentVersion()->firstOrFail()->change_note);
    }

    public function test_verkeerd_bestand_of_te_lange_toelichting_geeft_een_foutmelding(): void
    {
        $admin = $this->admin();
        $document = $this->uploadedDocument($this->project($this->client()), $admin);

        $this->uploadVersion($admin, $document, ['file' => UploadedFile::fake()->create('pagina.html', 10, 'text/html')])
            ->assertSessionHasErrors(['file'], null, 'version');

        $this->uploadVersion($admin, $document, ['file' => null])
            ->assertSessionHasErrors(['file'], null, 'version');

        $this->uploadVersion($admin, $document, ['change_note' => str_repeat('a', 1001)])
            ->assertSessionHasErrors(['change_note'], null, 'version');

        $this->assertSame(1, $document->versions()->count());
    }

    public function test_klant_kan_geen_nieuwe_versie_uploaden(): void
    {
        $anna = $this->client();
        $document = $this->uploadedDocument($this->project($anna), $this->admin());

        $this->uploadVersion($anna, $document)->assertRedirect('/dashboard');

        $this->assertSame(1, $document->versions()->count());
    }

    // ---------- 3. Akkoord geven ----------

    public function test_klant_keurt_de_huidige_versie_goed(): void
    {
        $admin = $this->admin();
        $anna = $this->client();
        $document = $this->uploadedDocument($this->project($anna), $admin);
        $this->uploadVersion($admin, $document);
        $current = $document->currentVersion()->firstOrFail();

        $this->actingAs($anna)->post(route('documents.approve', $document), ['version_id' => $current->id])
            ->assertSessionHas('success');

        $this->assertSame('Akkoord', $document->fresh()->status);
        $current->refresh();
        $this->assertNotNull($current->approved_at);
        $this->assertSame($anna->id, (int) $current->approved_by);
        // De oude versie is niet goedgekeurd
        $this->assertNull($document->versions()->where('version_number', 1)->value('approved_at'));
    }

    public function test_akkoord_op_een_verouderde_versie_wordt_geweigerd(): void
    {
        $admin = $this->admin();
        $anna = $this->client();
        $document = $this->uploadedDocument($this->project($anna), $admin);
        $oldVersionId = $document->currentVersion()->value('document_versions.id');

        // De klant had versie 1 open, intussen uploadt GKR versie 2
        $this->uploadVersion($admin, $document);

        $this->actingAs($anna)->post(route('documents.approve', $document), ['version_id' => $oldVersionId])
            ->assertSessionHas('error');

        $this->assertSame('Verzonden', $document->fresh()->status);
        $this->assertSame(0, $document->versions()->whereNotNull('approved_at')->count());
    }

    public function test_akkoord_zonder_versie_wordt_geweigerd_als_er_versies_zijn(): void
    {
        $anna = $this->client();
        $document = $this->uploadedDocument($this->project($anna), $this->admin());

        $this->actingAs($anna)->post(route('documents.approve', $document))->assertSessionHas('error');

        $this->assertSame('Verzonden', $document->fresh()->status);
    }

    public function test_document_van_voor_het_versiebeheer_kan_nog_steeds_worden_goedgekeurd(): void
    {
        $anna = $this->client();
        $document = $this->oldDocument($this->project($anna));

        $this->actingAs($anna)->post(route('documents.approve', $document))->assertSessionHas('success');

        $this->assertSame('Akkoord', $document->fresh()->status);
    }

    public function test_een_andere_klant_krijgt_niet_gevonden(): void
    {
        $document = $this->uploadedDocument($this->project($this->client('Anna Achternaam')), $this->admin());
        $bram = $this->client('Bram Bakker');

        $this->actingAs($bram)->get(route('documents.show', $document))->assertNotFound();
        $this->actingAs($bram)->post(route('documents.approve', $document), [
            'version_id' => $document->currentVersion()->value('document_versions.id'),
        ])->assertNotFound();
        $this->actingAs($bram)->get(route('projects.show', $document->project_id))->assertNotFound();

        $this->assertSame('Verzonden', $document->fresh()->status);
    }

    // ---------- 4. Een (oude) versie openen ----------

    public function test_klant_en_medewerker_kunnen_een_oude_versie_openen(): void
    {
        $admin = $this->admin();
        $anna = $this->client();
        $document = $this->uploadedDocument($this->project($anna), $admin);
        $first = $document->currentVersion()->firstOrFail();
        $this->uploadVersion($admin, $document);

        foreach ([$anna, $admin] as $user) {
            $response = $this->actingAs($user)->get(route('documents.version.file', [$document, $first]))
                ->assertOk()
                ->assertHeader('X-Content-Type-Options', 'nosniff');

            // De naam van het bestand zegt welke versie het is
            $this->assertStringContainsString('versie 1', (string) $response->headers->get('Content-Disposition'));
        }
    }

    public function test_gast_en_andere_klant_kunnen_geen_versie_openen(): void
    {
        $document = $this->uploadedDocument($this->project($this->client()), $this->admin());
        $version = $document->currentVersion()->firstOrFail();
        $url = route('documents.version.file', [$document, $version]);

        // uploadedDocument() logde in als medewerker: eerst uitloggen, anders is dit geen gast
        auth()->logout();

        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs($this->client('Bram Bakker'))->get($url)->assertNotFound();
    }

    public function test_versie_van_een_ander_document_geeft_niet_gevonden(): void
    {
        $admin = $this->admin();
        $anna = $this->client('Anna Achternaam');
        $bram = $this->client('Bram Bakker');
        $annasDocument = $this->uploadedDocument($this->project($anna), $admin, 'Document van Anna');
        $bramsDocument = $this->uploadedDocument($this->project($bram), $admin, 'Document van Bram');

        // Anna gebruikt haar eigen document, maar met het versienummer van Bram
        $bramsVersion = $bramsDocument->currentVersion()->firstOrFail();

        $this->actingAs($anna)->get(route('documents.version.file', [$annasDocument, $bramsVersion]))->assertNotFound();
    }

    // ---------- 5. Reacties per versie ----------

    public function test_reactie_onthoudt_bij_welke_versie_hij_hoort(): void
    {
        $admin = $this->admin();
        $anna = $this->client();
        $document = $this->uploadedDocument($this->project($anna), $admin);

        $this->actingAs($anna)->post(route('comments.store', $document), ['content' => 'Logo mag groter']);
        $this->uploadVersion($admin, $document);
        $this->actingAs($anna)->post(route('comments.store', $document), ['content' => 'Nu is het goed']);

        $this->assertSame(1, Comment::where('content', 'Logo mag groter')->firstOrFail()->version->version_number);
        $this->assertSame(2, Comment::where('content', 'Nu is het goed')->firstOrFail()->version->version_number);

        $this->actingAs($anna)->get(route('documents.show', $document))
            ->assertOk()
            ->assertSee('bij versie 1')
            ->assertSee('bij versie 2');
    }

    public function test_versie_kan_niet_via_het_formulier_worden_meegestuurd(): void
    {
        $admin = $this->admin();
        $anna = $this->client();
        $document = $this->uploadedDocument($this->project($anna), $admin);
        $first = $document->currentVersion()->firstOrFail();
        $this->uploadVersion($admin, $document);

        $this->actingAs($anna)->post(route('comments.store', $document), [
            'content'             => 'Reactie',
            'document_version_id' => $first->id,
        ]);

        $this->assertSame(2, Comment::firstOrFail()->version->version_number);
    }

    // ---------- 6. Wat de pagina's laten zien ----------

    public function test_klantpagina_toont_versies_toelichting_en_de_juiste_versie_in_het_formulier(): void
    {
        $admin = $this->admin();
        $anna = $this->client();
        $document = $this->uploadedDocument($this->project($anna), $admin);
        $this->uploadVersion($admin, $document, ['change_note' => 'Kleur van de knoppen aangepast']);
        $current = $document->currentVersion()->firstOrFail();

        $this->actingAs($anna)->get(route('documents.show', $document))
            ->assertOk()
            ->assertSee('Versie 2')
            ->assertSee('Versie 1')
            ->assertSee('Wat is er veranderd in versie 2?')
            ->assertSee('Kleur van de knoppen aangepast')
            ->assertSee('name="version_id" value="' . $current->id . '"', false)
            ->assertSee(route('documents.version.file', [$document, $current]), false)
            // Uploaden is alleen voor GKR
            ->assertDontSee('Nieuwe versie uploaden');
    }

    public function test_klant_ziet_dat_hij_een_eerdere_versie_al_goedkeurde(): void
    {
        $admin = $this->admin();
        $anna = $this->client();
        $document = $this->uploadedDocument($this->project($anna), $admin);
        $this->actingAs($anna)->post(route('documents.approve', $document), ['version_id' => $document->currentVersion()->value('document_versions.id')]);
        $this->uploadVersion($admin, $document);

        $this->actingAs($anna)->get(route('documents.show', $document))
            ->assertSee('Je gaf eerder akkoord op versie 1');
    }

    public function test_medewerkerpagina_toont_uploadformulier_en_wie_uploadde(): void
    {
        $admin = $this->admin('Stijn Medewerker');
        $document = $this->uploadedDocument($this->project($this->client()), $admin);

        $this->actingAs($admin)->get(route('admin.documents.show', $document))
            ->assertOk()
            ->assertSee('Nieuwe versie uploaden')
            ->assertSee('Versie 2 uploaden')
            ->assertSee('geüpload door Stijn Medewerker');
    }

    public function test_toelichting_wordt_nooit_als_code_uitgevoerd(): void
    {
        $admin = $this->admin();
        $anna = $this->client();
        $document = $this->uploadedDocument($this->project($anna), $admin);
        $this->uploadVersion($admin, $document, ['change_note' => '<script>alert("hack")</script>']);

        $this->actingAs($anna)->get(route('documents.show', $document))
            ->assertOk()
            ->assertDontSee('<script>alert("hack")</script>', false)
            ->assertSee('&lt;script&gt;', false);
    }

    // ---------- 7. Bestaande documenten omzetten (migratie) ----------

    public function test_bestaande_documenten_worden_versie_1(): void
    {
        $anna = $this->client();
        $project = $this->project($anna);
        $approved = $this->oldDocument($project, 'Akkoord');
        $comment = Comment::create(['document_id' => $approved->id, 'user_id' => $anna->id, 'content' => 'Oude reactie', 'is_resolved' => false]);

        DB::table('documents')->insert([
            'project_id' => $project->id, 'name' => 'Nog niet goedgekeurd', 'file_path' => 'documents/b.pdf',
            'status' => 'Verzonden', 'approved_at' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/' . self::MIGRATION);
        $migration->backfill();
        // Veilig om twee keer te draaien
        $migration->backfill();

        $this->assertSame(2, DocumentVersion::count());

        $first = DocumentVersion::where('document_id', $approved->id)->firstOrFail();
        $this->assertSame(1, $first->version_number);
        $this->assertSame('documents/oud.pdf', $first->file_path);
        $this->assertNotNull($first->approved_at);
        $this->assertSame($first->id, (int) $comment->fresh()->document_version_id);

        $this->assertNull(DocumentVersion::where('file_path', 'documents/b.pdf')->value('approved_at'));
    }
}