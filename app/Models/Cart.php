<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A shopper's in-progress basket. Identified by an opaque token in a cookie,
 * so it survives session expiry and can be claimed by a customer who signs in
 * partway through.
 *
 * The cart holds items only — every money figure (discount, shipping, tax,
 * total) is computed on demand by PricingService, so a stale stored total can
 * never disagree with what checkout actually charges.
 */
class Cart extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'converted_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function getItemCountAttribute(): int
    {
        return (int) $this->items->sum('quantity');
    }

    public function getIsEmptyAttribute(): bool
    {
        return $this->items->isEmpty();
    }

    /** Total shipping weight, for weight-banded shipping rates. */
    public function getWeightGramsAttribute(): int
    {
        return (int) $this->items->sum(
            fn (CartItem $item) => ($item->variant?->weight_grams ?? 0) * $item->quantity
        );
    }
}
