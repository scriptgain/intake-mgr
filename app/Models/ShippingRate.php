<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingRate extends Model
{
    public const TYPES = ['flat', 'weight', 'price', 'free'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Is this rate offered for a cart of the given subtotal and weight?
     * Banded rates compare against whichever measure their type names.
     */
    public function availableFor(int $subtotalCents, int $weightGrams): bool
    {
        $measure = match ($this->type) {
            'weight' => $weightGrams,
            'price' => $subtotalCents,
            default => null,
        };

        if ($measure === null) {
            return true; // flat + free are always offered
        }

        if ($this->min_value !== null && $measure < $this->min_value) {
            return false;
        }

        if ($this->max_value !== null && $measure > $this->max_value) {
            return false;
        }

        return true;
    }

    /** What this rate charges a cart, honouring the free-above threshold. */
    public function priceFor(int $subtotalCents): int
    {
        if ($this->type === 'free') {
            return 0;
        }

        if ($this->free_above_cents !== null && $subtotalCents >= $this->free_above_cents) {
            return 0;
        }

        return (int) $this->price_cents;
    }

    /* ---- Form inputs -------------------------------------------------
       Unsymboled editable strings for the zone editor. Band bounds are money
       for a price band and plain grams for a weight band, so each renders in
       the unit the merchant actually typed. */

    public function getPriceInputAttribute(): string
    {
        return Money::format($this->price_cents, false);
    }

    public function getFreeAboveInputAttribute(): string
    {
        return $this->free_above_cents !== null ? Money::format($this->free_above_cents, false) : '';
    }

    public function getMinValueInputAttribute(): string
    {
        return $this->bandInput($this->min_value);
    }

    public function getMaxValueInputAttribute(): string
    {
        return $this->bandInput($this->max_value);
    }

    private function bandInput(?int $value): string
    {
        if ($value === null) {
            return '';
        }

        return $this->type === 'price' ? Money::format($value, false) : (string) $value;
    }

    public function getPriceFormattedAttribute(): string
    {
        return $this->price_cents > 0 ? Money::format($this->price_cents) : 'Free';
    }
}
