<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Een dag waarop heel GKR gesloten is (feestdag, kerstsluiting). ADR-011.
 */
class ClosedDay extends Model
{
    use HasFactory;

    protected $fillable = ['date', 'reason'];

    protected $casts = [
        'date' => 'date:Y-m-d',
    ];
}
