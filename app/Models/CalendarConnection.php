<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A staff member's link to an external calendar (Google, Microsoft, Apple
 * CalDAV, or Nylas). Secrets are encrypted at rest via the cast, mirroring
 * User::two_factor_secret.
 */
class CalendarConnection extends Model
{
    public const PROVIDERS = ['google', 'microsoft', 'apple', 'nylas'];

    public const PROVIDER_LABELS = [
        'google' => 'Google Calendar',
        'microsoft' => 'Microsoft Outlook',
        'apple' => 'Apple iCloud',
        'nylas' => 'Nylas',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'caldav_password' => 'encrypted',
            'token_expires_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function syncedEvents(): HasMany
    {
        return $this->hasMany(CalendarSyncedEvent::class);
    }

    public function busyBlocks(): HasMany
    {
        return $this->hasMany(CalendarBusyBlock::class);
    }

    /** Access token needs refreshing (expired or within a 2-minute skew). */
    public function isExpired(): bool
    {
        return $this->token_expires_at !== null
            && $this->token_expires_at->subMinutes(2)->isPast();
    }

    public function getProviderLabelAttribute(): string
    {
        return self::PROVIDER_LABELS[$this->provider] ?? ucfirst((string) $this->provider);
    }

    public function getStatusBadgeAttribute(): string
    {
        return [
            'connected' => 'success',
            'error' => 'danger',
            'revoked' => 'neutral',
        ][$this->status] ?? 'neutral';
    }
}
