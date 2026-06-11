<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppointmentOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'appointment_id',
        'start_time',
        'end_time'
    ];

    // Zorg ervoor dat Laravel de datums automatisch omzet naar Carbon-objecten
    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    // Relatie terug naar de hoofd-afspraak
    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    // Relatie naar de 3 voorgestelde momenten
public function options()
{
    return $this->hasMany(AppointmentOption::class);
}
}