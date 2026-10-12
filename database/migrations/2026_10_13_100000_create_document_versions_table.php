<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Versiebeheer voor projectdocumenten.
 *
 * - Nieuwe tabel document_versions: elke upload van een document is een versie (1, 2, 3...).
 *   Oude versies blijven bewaard, inclusief wanneer en door wie ze zijn goedgekeurd.
 * - De tabel documents blijft hetzelfde: daar staan altijd het bestand en de status van de
 *   huidige (nieuwste) versie. Zo blijven overzichten en dashboards gewoon werken.
 * - Elke reactie onthoudt bij welke versie hij is geplaatst (comments.document_version_id).
 * - Bestaande documenten worden "versie 1", met de goedkeuring die er al was.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('file_path');

            // Optioneel: wat is er veranderd ten opzichte van de vorige versie
            $table->text('change_note')->nullable();

            // Wie uploadde deze versie (leeg als die medewerker niet meer bestaat)
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            // Het digitale akkoord van de klant op precies deze versie
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Elk versienummer komt per document maar één keer voor
            $table->unique(['document_id', 'version_number']);
        });

        Schema::table('comments', function (Blueprint $table) {
            // Bij welke versie hoort deze reactie? Leeg bij heel oude reacties.
            $table->foreignId('document_version_id')->nullable()->after('document_id')
                ->constrained('document_versions')->nullOnDelete();
        });

        $this->backfill();
    }

    /**
     * Maak van elk bestaand document "versie 1" en koppel de bestaande reacties daaraan.
     * Veilig om vaker te draaien: een document dat al versies heeft, wordt overgeslagen.
     * Public, zodat een test dit los kan aanroepen.
     */
    public function backfill(): void
    {
        $documents = DB::table('documents')->orderBy('id')->get();

        foreach ($documents as $document) {
            if (DB::table('document_versions')->where('document_id', $document->id)->exists()) {
                continue;
            }

            $versionId = DB::table('document_versions')->insertGetId([
                'document_id'    => $document->id,
                'version_number' => 1,
                'file_path'      => $document->file_path,
                'change_note'    => null,
                'uploaded_by'    => null,
                // Alleen een document dat nu goedgekeurd is, had een geldig akkoord
                'approved_at'    => $document->status === 'Akkoord' ? $document->approved_at : null,
                'approved_by'    => null,
                'created_at'     => $document->created_at,
                'updated_at'     => $document->updated_at,
            ]);

            DB::table('comments')
                ->where('document_id', $document->id)
                ->whereNull('document_version_id')
                ->update(['document_version_id' => $versionId]);
        }
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('document_version_id');
        });

        Schema::dropIfExists('document_versions');
    }
};