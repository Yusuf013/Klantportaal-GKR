<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentVersion;
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
 *
 * show()    = de huidige versie van het document
 * version() = een bepaalde (ook oudere) versie, zie versiebeheer
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
        $this->authorizeDocument($request, $document);

        return $this->respond($document->file_path, $this->baseName($document));
    }

    /**
     * Een bepaalde versie van het document. Dezelfde controles als hierboven, plus:
     * de versie moet echt bij dit document horen. Zo kan niemand via het nummer van
     * zijn eigen document bij de versie van een ander document komen.
     */
    public function version(Request $request, Document $document, DocumentVersion $version)
    {
        $this->authorizeDocument($request, $document);

        abort_unless((int) $version->document_id === (int) $document->id, 404);

        return $this->respond($version->file_path, $this->baseName($document) . ' (versie ' . $version->version_number . ')');
    }

    /**
     * Alleen GKR en de klant van het project. Ieder ander: 404.
     */
    private function authorizeDocument(Request $request, Document $document): void
    {
        $user = $request->user();

        $isOwner = (int) ($document->project?->user_id ?? 0) === (int) $user->id;

        abort_unless($user->isAdmin() || $isOwner, 404);
    }

    private function respond(?string $path, string $name)
    {
        // Geen pad, of een pad dat buiten de map probeert te komen: niet tonen
        abort_if(blank($path) || str_contains($path, '..'), 404);

        $disk = $this->diskOf($path);

        abort_if($disk === null, 404);

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return Storage::disk($disk)->response(
            $path,
            $extension !== '' ? "{$name}.{$extension}" : $name,
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
     * (zonder extensie). Tekens die niet in een bestandsnaam horen, halen we weg.
     */
    private function baseName(Document $document): string
    {
        $name = trim(preg_replace('/[\/\\\\:*?"<>|\x00-\x1F]+/', '-', (string) $document->name), ' .-');

        return $name === '' ? 'document' : $name;
    }
}