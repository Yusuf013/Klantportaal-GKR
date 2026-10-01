<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdAccount extends Model
{
    public const PLATFORM_META = 'meta';
    public const PLATFORM_GOOGLE_ADS = 'google_ads';

    // user_id staat hier bewust NIET in: die wordt altijd via de relatie gezet
    protected $fillable = ['platform', 'account_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}