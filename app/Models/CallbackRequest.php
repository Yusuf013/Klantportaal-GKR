<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een belverzoek van een klant die een afspraak op locatie wil (design "Belverzoek indienen").
 */
class CallbackRequest extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';

    public const STATUS_AFGEHANDELD = 'afgehandeld';

    // user_id, status en handled_by worden altijd server-side gezet, nooit uit de request.
    protected $fillable = ['project_id', 'phone', 'note'];

    protected $casts = [
        'handled_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
