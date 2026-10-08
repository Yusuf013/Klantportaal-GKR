<?php

namespace App\Services;

use App\Models\Branding;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Vervangt of verwijdert het logo van een branding. Eén plek voor de volgorde, zodat een
 * mislukte stap nooit een record oplevert dat naar een niet-bestaand bestand wijst:
 * nieuw bestand opslaan → record bijwerken → pas daarna het oude bestand opruimen.
 *
 * Bestandsnamen kiest de server (willekeurige hash, nooit de naam van de client). Elke upload
 * krijgt dus een nieuwe URL, waardoor caches bij clients vanzelf invalideren.
 */
class BrandingLogoService
{
    public function replace(Branding $branding, UploadedFile $file): Branding
    {
        $previous = $branding->logo_path;
        $stored = $file->store($branding->logoDirectory(), config('branding.disk'));

        if ($stored === false) {
            throw new RuntimeException('Het logo kon niet worden opgeslagen.');
        }

        try {
            $branding->logo_path = $stored;
            $branding->save();
        } catch (Throwable $e) {
            $this->deleteQuietly($stored);

            throw $e;
        }

        $this->deleteQuietly($previous);

        return $branding;
    }

    public function remove(Branding $branding): Branding
    {
        $previous = $branding->logo_path;

        if ($previous === null) {
            return $branding;
        }

        $branding->logo_path = null;
        $branding->save();

        $this->deleteQuietly($previous);

        return $branding;
    }

    /**
     * Best effort: het record wijst al naar het nieuwe (of geen) bestand, dus een achtergebleven
     * oud bestand is rommel, geen fout voor de gebruiker.
     */
    private function deleteQuietly(?string $path): void
    {
        if ($path === null) {
            return;
        }

        if (! Storage::disk(config('branding.disk'))->delete($path)) {
            Log::warning('Oud brandinglogo kon niet worden verwijderd.', ['path' => $path]);
        }
    }
}
