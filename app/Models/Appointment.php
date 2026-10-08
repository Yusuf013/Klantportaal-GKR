<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Appointment extends Model
{
    use HasFactory;

    public const TYPE_TELEFOON = 'telefoon';

    public const TYPE_ONLINE = 'online';

    public const TYPE_FYSIEK = 'fysiek';

    public const LOCATION_BIJ_GKR = 'bij_gkr';

    public const LOCATION_OP_LOCATIE = 'op_locatie';

    public const SYNC_NOT_REQUIRED = 'not_required';

    public const SYNC_PENDING = 'pending';

    public const SYNC_SYNCED = 'synced';

    public const SYNC_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'organizer_user_id',
        'project_id',
        'title',
        'type',
        'location',
        'travel_minutes',
        'start_time',
        'end_time',
        'description',
        'status',
        'zoom_link',
        'online_meeting_url',
        'outlook_event_id',
        'ical_uid',
        'calendar_sync_status',
        'calendar_synced_at',
        'calendar_sync_error',
    ];

    // `status` blijft bewust een platte string; zie App\Enums\AppointmentStatus.
    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'travel_minutes' => 'integer',
        'calendar_synced_at' => 'datetime',
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

    // De GKR-medewerker vanaf wiens werkmail de Outlook-uitnodiging wordt verstuurd
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_user_id');
    }

    // De GKR-medewerkers (Admins) die bij deze afspraak zijn uitgenodigd
    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'appointment_user');
    }

    /**
     * Relatie naar de gekoppelde GKR-medewerkers (Admins) voor de agenda-check
     */
    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'appointment_user', 'appointment_id', 'user_id');
    }

    /**
     * Een afspraak (voorstel) kan meerdere voorgestelde tijdslots (opties) hebben.
     */
    public function options(): HasMany
    {
        return $this->hasMany(AppointmentOption::class, 'appointment_id');
    }

    // Reistijdblokken in Outlook
    public function calendarEvents(): HasMany
    {
        return $this->hasMany(AppointmentCalendarEvent::class);
    }

    /**
     * Afspraken waarin deze medewerker organisator of deelnemer is ("Mijn afspraken").
     * Gedeeld door website en API, zodat beide hetzelfde tonen.
     */
    public function scopeForEmployee(Builder $query, User $employee): Builder
    {
        return $query->where(function (Builder $q) use ($employee) {
            $q->where('organizer_user_id', $employee->id)
                ->orWhereHas('attendees', fn (Builder $a) => $a->where('users.id', $employee->id));
        });
    }

    /**
     * Alle betrokken GKR-medewerkers: de organisator plus de deelnemers, zonder dubbelen.
     *
     * @return Collection<int, User>
     */
    public function internalParticipants(): Collection
    {
        $participants = $this->attendees->keyBy('id');

        if ($this->organizer && ! $participants->has($this->organizer->id)) {
            $participants->prepend($this->organizer, $this->organizer->id);
        }

        return new Collection($participants->values()->all());
    }

    public function durationMinutes(): int
    {
        return (int) $this->start_time->diffInMinutes($this->end_time);
    }

    /**
     * Reistijd vóór en na de afspraak; alleen bij een fysieke afspraak op locatie.
     */
    public function effectiveTravelMinutes(): int
    {
        return $this->type === self::TYPE_FYSIEK && $this->location === self::LOCATION_OP_LOCATIE
            ? (int) $this->travel_minutes
            : 0;
    }

    /**
     * Het tijdvak dat deze afspraak in de agenda van de medewerkers bezet houdt.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function occupiedWindow(): array
    {
        $travel = $this->effectiveTravelMinutes();

        return [
            CarbonImmutable::parse($this->start_time)->subMinutes($travel),
            CarbonImmutable::parse($this->end_time)->addMinutes($travel),
        ];
    }

    /**
     * "dinsdag 20 oktober 2026 om 10:00 - 11:00 uur", in de tijdzone van GKR en altijd in het
     * Nederlands. Voor de detailpopups op de website: de server maakt het op, zodat de browser
     * niet met tijdzones hoeft te rekenen (een bezoeker buiten Nederland zag anders andere tijden).
     */
    public function momentLabel(): string
    {
        $timezone = config('app.timezone');
        $start = $this->start_time->copy()->setTimezone($timezone)->locale('nl');
        $end = $this->end_time?->copy()->setTimezone($timezone);

        return $start->translatedFormat('l j F Y').' om '.$start->format('H:i')
            .($end ? ' - '.$end->format('H:i') : '').' uur';
    }

    public function locationLabel(): string
    {
        return match ($this->type) {
            self::TYPE_TELEFOON => 'Telefonisch',
            self::TYPE_ONLINE => 'Microsoft Teams-vergadering',
            default => (string) config('appointments.locations.'.($this->location ?? self::LOCATION_BIJ_GKR)),
        };
    }

    public function hasStatus(AppointmentStatus ...$statuses): bool
    {
        foreach ($statuses as $status) {
            if ($this->status === $status->value) {
                return true;
            }
        }

        return false;
    }
}
