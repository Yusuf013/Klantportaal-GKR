<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Appointment extends Model
{
    // Voeg hier user_id en project_id toe aan de array
    protected $fillable = [
        'user_id',
        'project_id',
        'title',
        'type',
        'start_time',
        'end_time',
        'description',
        'status',
        'zoom_link',
        'outlook_event_id',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    // De klant die de afspraak heeft aangevraagd
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Het project waar deze afspraak onder valt
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    // De GKR-medewerkers (Admins) die bij deze afspraak zijn uitgenodigd
    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'appointment_user');
    }
}