<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use \App\Models\Concerns\Auditable;
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function hasTwoFactor(): bool
    {
        return $this->two_factor_confirmed_at !== null && ! empty($this->two_factor_secret);
    }

    /* ---- Calendar + availability ------------------------------------- */

    public function calendarConnections(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CalendarConnection::class);
    }

    public function availabilityRules(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AvailabilityRule::class)->orderBy('weekday')->orderBy('start_time');
    }

    public function availabilityExceptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AvailabilityException::class)->orderBy('date');
    }

    /** The timezone for this staff member's schedule, falling back to the business default. */
    public function effectiveTimezone(): string
    {
        return $this->timezone ?: (string) config('shop.timezone', config('app.timezone', 'UTC'));
    }

    /** The staff member's secret calendar-feed token, minted on first use. */
    public function feedToken(): string
    {
        if (! $this->calendar_feed_token) {
            $this->forceFill(['calendar_feed_token' => \Illuminate\Support\Str::random(48)])->save();
        }

        return $this->calendar_feed_token;
    }

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
            'two_factor_secret' => 'encrypted',
            'two_factor_confirmed_at' => 'datetime',
            'password_changed_at' => 'datetime',
        ];
    }
}
