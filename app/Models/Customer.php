<?php

namespace App\Models;

use App\Notifications\CustomerPasswordReset;
use App\Support\Money;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * A storefront shopper. Authenticates on the 'customer' guard, which is a
 * different session than the staff 'web' guard: a customer signing in on the
 * shop can never end up holding an admin session.
 */
class Customer extends Authenticatable implements CanResetPasswordContract
{
    use CanResetPassword;
    use Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'first_name', 'last_name', 'email', 'phone', 'password',
        'accepts_marketing', 'notes',
        'default_shipping_address', 'default_billing_address',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'accepts_marketing' => 'boolean',
            'default_shipping_address' => 'array',
            'default_billing_address' => 'array',
            'last_order_at' => 'datetime',
            'email_verified_at' => 'datetime',
        ];
    }

    /**
     * Deliver the reset link through our own notification so the URL points at
     * the storefront reset route (shop.account.reset) on the customer guard,
     * not the staff 'password.reset' route the framework default assumes.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new CustomerPasswordReset($token));
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)->latest();
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class)->latest();
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class)->latest();
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class)->latest();
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class)->latest();
    }

    /* ---- Derived, view-ready ------------------------------------------ */

    public function getNameAttribute(): string
    {
        $name = trim(($this->first_name ?? '').' '.($this->last_name ?? ''));

        return $name ?: $this->email;
    }

    public function getInitialsAttribute(): string
    {
        $parts = array_filter([$this->first_name, $this->last_name]);

        if (! $parts) {
            return mb_strtoupper(mb_substr($this->email, 0, 2));
        }

        return mb_strtoupper(implode('', array_map(fn ($p) => mb_substr($p, 0, 1), $parts)));
    }

    public function getTotalSpentFormattedAttribute(): string
    {
        return Money::format($this->total_spent_cents);
    }

    /** A shopper who registered rather than checking out as a guest. */
    public function getHasAccountAttribute(): bool
    {
        return ! empty($this->password);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('email', 'like', "%{$term}%")
                ->orWhere('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%");
        });
    }

    /** Recompute the cached order rollups from paid orders. */
    public function refreshTotals(): void
    {
        $paid = $this->orders()
            ->whereIn('financial_status', ['paid', 'partially_refunded'])
            ->get();

        $this->forceFill([
            'orders_count' => $paid->count(),
            'total_spent_cents' => (int) $paid->sum(fn (Order $o) => $o->total_cents - $o->refunded_cents),
            'last_order_at' => $paid->max('created_at'),
        ])->save();
    }
}
