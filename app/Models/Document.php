<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Document extends Model
{
    /**
     * De velden die via een formulier gevuld mogen worden.
     * Dit is belangrijk voor de veiligheid van je database.
     */
protected $fillable = ['project_id', 'name', 'file_path', 'status', 'approved_at'];

    /**
     * Relatie: Een document hoort bij één specifiek project.
     * Dit zorgt ervoor dat we later kunnen zeggen: $document->project->name
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    protected $casts = [
    'approved_at' => 'datetime',
];

public function comments(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(Comment::class)->latest();
}

    /**
     * Alle versies van dit document, nieuwste eerst (versie 3, 2, 1).
     */
    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class)->orderByDesc('version_number');
    }

    /**
     * De huidige versie: die met het hoogste versienummer.
     * Leeg bij een document dat nog geen versies heeft (van voor het versiebeheer).
     */
    public function currentVersion(): HasOne
    {
        return $this->hasOne(DocumentVersion::class)->ofMany('version_number', 'max');
    }

}