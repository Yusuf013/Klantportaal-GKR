<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'sender_id',
        'receiver_id',
        'body',
        'file_path',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    // De zender van het bericht
    public function sender(): BelongsTo
    {
        return $table = $this->belongsTo(User::class, 'sender_id');
    }

    // De ontvanger van het bericht
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }
}