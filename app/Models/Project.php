<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'name', 'status', 'progress', 'deadline'])]
class Project extends Model
{
    /**
     * Een project hoort altijd bij één specifieke gebruiker (klant).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}