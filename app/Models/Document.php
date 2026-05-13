<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

}