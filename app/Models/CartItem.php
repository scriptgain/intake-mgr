<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected $guarded = ['id'];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function getLineTotalCentsAttribute(): int
    {
        return $this->unit_price_cents * $this->quantity;
    }

    public function getLineTotalFormattedAttribute(): string
    {
        return Money::format($this->line_total_cents);
    }

    public function getUnitPriceFormattedAttribute(): string
    {
        return Money::format($this->unit_price_cents);
    }

    /**
     * How many units the shopper may hold, capped by stock. Null = unlimited.
     * Drives the quantity <select> without any logic in the Blade view.
     */
    public function getMaxQuantityAttribute(): ?int
    {
        return $this->variant?->purchasableQuantity();
    }

    /** True when stock dropped below what's in the basket since it was added. */
    public function getIsOverstockedAttribute(): bool
    {
        $max = $this->max_quantity;

        return $max !== null && $this->quantity > $max;
    }

    /** True when the live price no longer matches what was captured on add. */
    public function getIsRepricedAttribute(): bool
    {
        return $this->variant !== null
            && $this->variant->price_cents !== $this->unit_price_cents;
    }
}
