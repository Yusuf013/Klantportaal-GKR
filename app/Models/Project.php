<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'name', 'status', 'progress', 'deadline'])]
class Project extends Model
{
    use HasFactory;

    /**
     * Een project hoort altijd bij één specifieke gebruiker (klant).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documents()
{
    return $this->hasMany(Document::class);
}

    public function appointments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}