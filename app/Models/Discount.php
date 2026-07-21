<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A discount code. See the migration for how `value` is interpreted per type.
 *
 * Eligibility lives here (not in the checkout controller) so the storefront,
 * the admin's manual-order screen, and the API all reach the same verdict.
 */
class Discount extends Model
{
    use \App\Models\Concerns\Auditable;

    public const TYPES = ['percentage', 'fixed_amount', 'free_shipping'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'target_ids' => 'array',
            'is_active' => 'boolean',
            'once_per_customer' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(DiscountRedemption::class);
    }

    protected static function booted(): void
    {
        // Codes are matched case-insensitively; normalise on the way in so the
        // unique index does the enforcing.
        static::saving(fn (Discount $d) => $d->code = strtoupper(trim($d->code)));
    }

    public static function findByCode(?string $code): ?self
    {
        if (blank($code)) {
            return null;
        }

        return static::where('code', strtoupper(trim($code)))->first();
    }

    /* ---- Eligibility --------------------------------------------------- */

    public function getIsScheduledAttribute(): bool
    {
        return $this->starts_at && $this->starts_at->isFuture();
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->ends_at && $this->ends_at->isPast();
    }

    public function getIsExhaustedAttribute(): bool
    {
        return $this->usage_limit !== null && $this->used_count >= $this->usage_limit;
    }

    /** A single word for the status pill: Active / Scheduled / Expired / ... */
    public function getStateAttribute(): string
    {
        return match (true) {
            ! $this->is_active => 'disabled',
            $this->is_expired => 'expired',
            $this->is_scheduled => 'scheduled',
            $this->is_exhausted => 'exhausted',
            default => 'active',
        };
    }

    public function getStateBadgeAttribute(): string
    {
        return [
            'active' => 'success',
            'scheduled' => 'info',
            'expired' => 'neutral',
            'exhausted' => 'warn',
            'disabled' => 'neutral',
        ][$this->state] ?? 'neutral';
    }

    /**
     * Whether this code may be applied right now, for this subtotal and (when
     * known) this customer. Returns an error string, or null when eligible.
     */
    public function rejectionReason(int $subtotalCents, ?Customer $customer = null, ?string $email = null): ?string
    {
        if ($this->state !== 'active') {
            return match ($this->state) {
                'expired' => 'That code has expired.',
                'scheduled' => 'That code is not active yet.',
                'exhausted' => 'That code has reached its usage limit.',
                default => 'That code is not available.',
            };
        }

        if ($this->min_subtotal_cents && $subtotalCents < $this->min_subtotal_cents) {
            return 'Orders must be at least '.Money::format($this->min_subtotal_cents).' to use this code.';
        }

        $perCustomer = $this->once_per_customer ? 1 : $this->usage_limit_per_customer;

        if ($perCustomer && ($customer || $email)) {
            $used = $this->redemptions()
                ->when($customer, fn ($q) => $q->where('customer_id', $customer->id))
                ->when(! $customer && $email, fn ($q) => $q->where('email', $email))
                ->count();

            if ($used >= $perCustomer) {
                return 'You have already used this code.';
            }
        }

        return null;
    }

    /** Line items this discount is allowed to touch. */
    public function appliesToItem(Product $product): bool
    {
        $targets = $this->target_ids ?? [];

        return match ($this->applies_to) {
            'products' => in_array($product->id, $targets),
            'collections' => $product->collections->pluck('id')->intersect($targets)->isNotEmpty(),
            default => true,
        };
    }

    /* ---- Display ------------------------------------------------------- */

    public function getValueLabelAttribute(): string
    {
        return match ($this->type) {
            'percentage' => Money::bpsToPercent((int) $this->value).' Off',
            'fixed_amount' => Money::format((int) $this->value).' Off',
            'free_shipping' => 'Free Shipping',
            default => 'Not set',
        };
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }
}
