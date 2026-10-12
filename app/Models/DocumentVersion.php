<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eén versie van een projectdocument (versie 1, 2, 3...).
 *
 * Het document zelf (tabel documents) wijst altijd naar de nieuwste versie. Oude versies
 * blijven hier bewaard, zodat zwart op wit terug te vinden is welke versie de klant
 * wanneer heeft goedgekeurd.
 *
 * Er is bewust geen $fillable: de controllers vullen elk veld zelf (forceFill), zodat
 * niemand via een formulier een ander document of een akkoord kan meesturen.
 */
class DocumentVersion extends Model
{
    protected $casts = [
        'approved_at'    => 'datetime',
        'version_number' => 'integer',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    // De medewerker die deze versie uploadde (kan leeg zijn)
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // De klant die deze versie goedkeurde (kan leeg zijn)
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}