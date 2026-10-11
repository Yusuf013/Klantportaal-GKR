<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Toont het bestand van een projectdocument, alleen aan wie het mag zien.
 *
 * Voorheen stonden de bestanden op de openbare schijf en linkten de pagina's er direct
 * naartoe (/storage/documents/...). Wie zo'n link had, kon het bestand openen zonder in
 * te loggen. Nu staan de bestanden op de privéschijf en gaat elk bestand via deze route.
 *
 * Wie mag een bestand zien?
 * - een medewerker van GKR;
 * - de klant van wie het project is.
 * Ieder ander krijgt "niet gevonden" (404), zodat je niet kunt raden welke documenten bestaan.
 */
class DocumentFileController extends Controller
{
    // Nieuwe bestanden staan op de privéschijf. Die is niet via /storage/... bereikbaar.
    public const DISK = 'local';

    // Alleen deze soorten toont de browser zelf (pdf en afbeeldingen). Dit zijn ook de
    // enige soorten die je kunt uploaden. Al het andere wordt als download aangeboden,
    // zodat een bestand met code erin nooit binnen het portaal wordt uitgevoerd.
    private const INLINE_TYPES = ['pdf', 'png', 'jpg', 'jpeg', 'webp'];

    public function show(Request $request, Document $document)
    {
        $user = $request->user();

        $isOwner = (int) ($document->project?->user_id ?? 0) === (int) $user->id;

        abort_unless($user->isAdmin() || $isOwner, 404);
        // Geen pad, of een pad dat buiten de map probeert te komen: niet tonen
        abort_if(blank($document->file_path) || str_contains($document->file_path, '..'), 404);

        $disk = $this->diskOf($document->file_path);

        abort_if($disk === null, 404);

        $extension = strtolower(pathinfo($document->file_path, PATHINFO_EXTENSION));

        return Storage::disk($disk)->response(
            $document->file_path,
            $this->downloadName($document, $extension),
            [
                // De browser moet het opgegeven type volgen en niet zelf gaan raden
                'X-Content-Type-Options' => 'nosniff',
                // Niet bewaren in gedeelde caches
                'Cache-Control'          => 'private, no-store',
            ],
            in_array($extension, self::INLINE_TYPES, true) ? 'inline' : 'attachment'
        );
    }

    /**
     * Op welke schijf staat dit bestand? Nieuwe bestanden staan op de privéschijf.
     * Een oud bestand dat nog niet is verhuisd, vinden we op de openbare schijf.
     */
    private function diskOf(string $path): ?string
    {
        foreach ([self::DISK, 'public'] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                return $disk;
            }
        }

        return null;
    }

    /**
     * De naam waaronder de browser het bestand opslaat: de naam van het document
     * met de juiste extensie. Tekens die niet in een bestandsnaam horen, halen we weg.
     */
    private function downloadName(Document $document, string $extension): string
    {
        $name = trim(preg_replace('/[\/\\\\:*?"<>|\x00-\x1F]+/', '-', (string) $document->name), ' .-');

        if ($name === '') {
            $name = 'document';
        }

        return $extension !== '' ? "{$name}.{$extension}" : $name;
    }
}