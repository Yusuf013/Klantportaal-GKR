<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eén chatbericht.
 *
 * Een gesprek is altijd tussen één klant en GKR als team. Bij welk gesprek een
 * bericht hoort, staat in client_id (de klant). Daardoor:
 * - ziet een klant alleen het eigen gesprek;
 * - zien alle medewerkers van GKR hetzelfde gesprek;
 * - hangt een gesprek niet aan het account van één medewerker.
 *
 * sender_id is wie het bericht schreef. Is dat de klant zelf (sender_id = client_id),
 * dan is het een bericht van de klant; anders is het een antwoord van GKR.
 * sender_id is leeg bij een antwoord van een medewerker die niet meer bestaat.
 */
class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
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

    protected static function booted(): void
    {
        // Vangnet: wordt er ergens een bericht gemaakt zonder gesprek, dan zoeken we
        // de klant er zelf bij. Zo bestaat er nooit een bericht dat in geen enkel
        // gesprek te zien is.
        static::creating(function (Message $message) {
            if (! $message->client_id) {
                $message->client_id = self::clientIdFor($message->sender_id, $message->receiver_id);
            }
        });
    }

    /**
     * De klant bij een bericht: degene van afzender en ontvanger die geen medewerker is.
     */
    public static function clientIdFor(?int $senderId, ?int $receiverId): ?int
    {
        $ids = array_values(array_filter([$senderId, $receiverId]));

        if (empty($ids)) {
            return null;
        }

        $adminIds = User::whereIn('id', $ids)->where('is_admin', true)->pluck('id')->all();

        foreach ($ids as $id) {
            if (! in_array($id, $adminIds)) {
                return (int) $id;
            }
        }

        return null;
    }

    // ---------- Relaties ----------

    // De klant van wie dit gesprek is
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    // De zender van het bericht
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    // De ontvanger van het bericht (alleen gevuld bij een antwoord van GKR)
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    // ---------- Gesprekken ----------

    /**
     * Alle berichten uit het gesprek van één klant.
     */
    public function scopeInConversation(Builder $query, int $clientId): Builder
    {
        return $query->where('client_id', $clientId);
    }

    /**
     * Berichten die de klant zelf schreef (dus niet de antwoorden van GKR).
     */
    public function scopeFromClient(Builder $query): Builder
    {
        return $query->whereNotNull('client_id')->whereColumn('sender_id', 'client_id');
    }

    /**
     * Antwoorden van GKR. Ook een antwoord zonder afzender (medewerker bestaat niet meer) telt mee.
     */
    public function scopeFromGkr(Builder $query): Builder
    {
        return $query->where(function (Builder $query) {
            $query->whereNull('sender_id')->orWhereColumn('sender_id', '!=', 'client_id');
        });
    }

    /**
     * Hoeveel ongelezen berichten heeft deze gebruiker?
     * - Medewerker: alle ongelezen berichten van klanten (GKR leest als team).
     * - Klant: de ongelezen antwoorden van GKR in het eigen gesprek.
     */
    public static function unreadCountFor(User $user): int
    {
        $query = self::query()->whereNull('read_at');

        return $user->isAdmin()
            ? $query->fromClient()->count()
            : $query->inConversation($user->id)->fromGkr()->count();
    }

    /**
     * Zet de berichten van "de andere kant" in dit gesprek op gelezen.
     * Leest een medewerker, dan zijn dat de berichten van de klant. Leest de klant,
     * dan zijn dat de antwoorden van GKR.
     */
    public static function markReadBy(User $reader, int $clientId): void
    {
        $query = self::query()->inConversation($clientId)->whereNull('read_at');

        if ($reader->isAdmin()) {
            $query->fromClient();
        } elseif ((int) $reader->id === $clientId) {
            $query->fromGkr();
        } else {
            return; // een klant kan nooit het gesprek van een ander als gelezen markeren
        }

        $query->update(['read_at' => now()]);
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