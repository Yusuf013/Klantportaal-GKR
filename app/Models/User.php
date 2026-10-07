<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'is_admin'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    // Alle advertentieaccounts van deze klant (Meta en Google Ads)
    public function adAccounts(): HasMany
    {
        return $this->hasMany(AdAccount::class);
    }

    // Alleen het Meta-account van deze klant
    public function metaAdAccount(): HasOne
    {
        return $this->hasOne(AdAccount::class)->where('platform', AdAccount::PLATFORM_META);
    }

    // Alleen het Google Ads-account van deze klant
    public function googleAdsAccount(): HasOne
    {
        return $this->hasOne(AdAccount::class)->where('platform', AdAccount::PLATFORM_GOOGLE_ADS);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * Berichten die door deze gebruiker zijn verzonden.
     */
    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    /**
     * Berichten die door deze gebruiker zijn ontvangen.
     */
    public function receivedMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'receiver_id');
    }

    /**
     * Controleer of de gebruiker een GKR Admin/Medewerker is.
     */
    public function isAdmin(): bool
    {
        return (bool) $this->is_admin; // Geeft true of false terug
    }
}