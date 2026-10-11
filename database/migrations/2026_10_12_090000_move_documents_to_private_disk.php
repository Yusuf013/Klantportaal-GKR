<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Verhuist de bestanden van projectdocumenten van de openbare schijf naar de privéschijf.
 *
 * Waarom: op de openbare schijf was elk bestand zonder inloggen te openen voor wie de
 * link had (/storage/documents/...). Na het verhuizen bestaat die link niet meer; het
 * bestand is alleen nog te openen via de beveiligde route (DocumentFileController).
 *
 * Er verandert niets in de database: het pad van het bestand blijft hetzelfde, alleen
 * de schijf is anders. Deze stap kan veilig vaker draaien.
 */
return new class extends Migration
{
    public function up(): void
    {
        $public = Storage::disk('public');
        $private = Storage::disk('local');

        $paths = DB::table('documents')->whereNotNull('file_path')->pluck('file_path')->unique();

        foreach ($paths as $path) {
            // Lege of vreemde paden slaan we over (bijv. met ".." erin)
            if (! is_string($path) || $path === '' || str_contains($path, '..')) {
                continue;
            }

            if (! $public->exists($path)) {
                continue; // staat al op de privéschijf, of het bestand is er niet meer
            }

            // Eerst kopiëren, dan pas het origineel weghalen: zo raakt er nooit een bestand kwijt
            if (! $private->exists($path)) {
                $stream = $public->readStream($path);

                $copied = $stream !== null && $private->writeStream($path, $stream);

                if (is_resource($stream)) {
                    fclose($stream);
                }

                if (! $copied || ! $private->exists($path)) {
                    Log::warning('Document kon niet naar de privéschijf worden verhuisd', ['path' => $path]);

                    continue; // het bestand blijft waar het was
                }
            }

            $public->delete($path);
        }
    }

    public function down(): void
    {
        // Bewust niets: bestanden worden niet opnieuw openbaar gemaakt.
    }
};