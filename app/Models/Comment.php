<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Comment extends Model
{
    // document_version_id wordt altijd door de server ingevuld (de huidige versie), nooit uit een formulier
    protected $fillable = ['document_id', 'document_version_id', 'user_id', 'content', 'is_resolved'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // De versie van het document waarbij deze reactie is geplaatst (kan leeg zijn)
    public function version(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'document_version_id');
    }
}