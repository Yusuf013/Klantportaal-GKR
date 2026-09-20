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
        'file_name',
        'file_size',
        'file_type',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    // De zender van het bericht
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    // De ontvanger van het bericht
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    /**
     * Berekent de bestandsgrootte in een leesbare indeling (KB of MB).
     */
    public function getFormattedFileSizeAttribute(): string
    {
        if (!$this->file_size) {
            return '';
        }

        if ($this->file_size >= 1048576) {
            return number_format($this->file_size / 1048576, 1) . ' MB';
        }

        return number_format($this->file_size / 1024, 0) . ' KB';
    }
}