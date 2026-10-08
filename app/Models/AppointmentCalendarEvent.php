<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een reistijdblok in de Outlook-agenda van één medewerker (FR-08, ADR-011).
 */
class AppointmentCalendarEvent extends Model
{
    public const KIND_TRAVEL_BEFORE = 'travel_before';

    public const KIND_TRAVEL_AFTER = 'travel_after';

    protected $fillable = ['appointment_id', 'user_id', 'kind', 'external_id'];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
