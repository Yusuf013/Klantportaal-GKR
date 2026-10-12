<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\DocumentFileController;
use App\Models\Document;
use App\Models\DocumentVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * GKR uploadt een nieuwe versie van een document.
 *
 * Wat er gebeurt:
 * - Het nieuwe bestand komt op de privéschijf, het oude blijft gewoon bewaard.
 * - Er komt een versie bij met het volgende nummer (2, 3, ...).
 * - Het document wijst voortaan naar het nieuwe bestand en gaat terug naar "Verzonden":
 *   de klant moet de nieuwe versie opnieuw beoordelen. Een eerder akkoord blijft
 *   zichtbaar bij de oude versie.
 */
class DocumentVersionController extends Controller
{
    // Maximale lengte van "wat is er veranderd" (in tekens)
    public const MAX_NOTE_LENGTH = 1000;

    public function store(Request $request, Document $document)
    {
        // Eigen "bag": zo komen de foutmeldingen alleen bij het formulier "Nieuwe versie"
        $validated = $request->validateWithBag('version', [
            // Dezelfde regels als bij de eerste upload
            'file'        => ['required', 'file', 'mimes:pdf,png,jpg,jpeg,webp', 'max:10240'],
            'change_note' => ['nullable', 'string', 'max:' . self::MAX_NOTE_LENGTH],
        ], [
            'file.required'   => 'Kies een bestand voor de nieuwe versie.',
            'file.mimes'      => 'Alleen pdf, png, jpg of webp is toegestaan.',
            'file.max'        => 'Het bestand mag hooguit 10 MB zijn.',
            'change_note.max' => 'De toelichting mag hooguit :max tekens lang zijn.',
        ]);

        // Eerst het bestand opslaan; lukt de rest daarna niet, dan ruimen we het weer op
        $path = $request->file('file')->store('documents', DocumentFileController::DISK);

        try {
            $number = DB::transaction(function () use ($document, $validated, $path, $request) {
                // Vergrendel het document, zodat twee uploads tegelijk geen gelijk nummer krijgen
                $document = Document::whereKey($document->id)->lockForUpdate()->firstOrFail();

                // Een document van voor het versiebeheer: het huidige bestand eerst als versie 1 bewaren
                if (! DocumentVersion::where('document_id', $document->id)->exists()) {
                    $first = new DocumentVersion();
                    $first->forceFill([
                        'document_id'    => $document->id,
                        'version_number' => 1,
                        'file_path'      => $document->file_path,
                        'approved_at'    => $document->status === 'Akkoord' ? $document->approved_at : null,
                    ])->save();
                }

                // Zonder sortering: MySQL weigert een MAX() met ORDER BY erbij
                $number = (int) DocumentVersion::where('document_id', $document->id)->max('version_number') + 1;

                $version = new DocumentVersion();
                $version->forceFill([
                    'document_id'    => $document->id,
                    'version_number' => $number,
                    'file_path'      => $path,
                    'change_note'    => filled($validated['change_note'] ?? null) ? trim($validated['change_note']) : null,
                    'uploaded_by'    => $request->user()->id,
                ])->save();

                // Het document wijst nu naar de nieuwe versie, die nog beoordeeld moet worden
                $document->update([
                    'file_path'   => $path,
                    'status'      => 'Verzonden',
                    'approved_at' => null,
                ]);

                return $number;
            });
        } catch (Throwable $e) {
            Storage::disk(DocumentFileController::DISK)->delete($path);

            throw $e;
        }

        return redirect()->route('admin.documents.show', $document)
            ->with('success', "Versie {$number} is geüpload. De klant ziet nu deze versie en kan opnieuw akkoord geven.");
    }
}