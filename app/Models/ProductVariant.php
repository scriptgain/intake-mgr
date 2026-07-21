<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id', 'name', 'sku', 'barcode',
        'option1_name', 'option1_value', 'option2_name', 'option2_value', 'option3_name', 'option3_value',
        'price_cents', 'compare_at_price_cents', 'cost_cents',
        'track_inventory', 'inventory_qty', 'weight_grams', 'is_default', 'position',
    ];

    protected function casts(): array
    {
        return [
            'track_inventory' => 'boolean',
            'is_default' => 'boolean',
            'price_cents' => 'integer',
            'compare_at_price_cents' => 'integer',
            'cost_cents' => 'integer',
            'inventory_qty' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Keep the human label in sync with the option values so order history
        // and the cart always show something readable.
        static::saving(function (ProductVariant $variant) {
            $label = collect([$variant->option1_value, $variant->option2_value, $variant->option3_value])
                ->filter()
                ->implode(' / ');

            $variant->name = $label ?: 'Default';
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /* ---- Stock -------------------------------------------------------- */

    public function getIsInStockAttribute(): bool
    {
        if (! $this->track_inventory) {
            return true;
        }

        return $this->inventory_qty > 0 || (bool) config('shop.allow_backorder');
    }

    public function getIsLowStockAttribute(): bool
    {
        return $this->track_inventory
            && $this->inventory_qty > 0
            && $this->inventory_qty <= (int) config('shop.low_stock_threshold', 5);
    }

    /** How many of this variant a shopper may add, given the stock policy. */
    public function purchasableQuantity(): ?int
    {
        if (! $this->track_inventory || config('shop.allow_backorder')) {
            return null; // unlimited
        }

        return max(0, $this->inventory_qty);
    }

    /* ---- Pricing ------------------------------------------------------ */

    public function getIsOnSaleAttribute(): bool
    {
        return $this->compare_at_price_cents !== null
            && $this->compare_at_price_cents > $this->price_cents;
    }

    public function getDiscountPercentAttribute(): int
    {
        if (! $this->is_on_sale || ! $this->compare_at_price_cents) {
            return 0;
        }

        return (int) round(
            (($this->compare_at_price_cents - $this->price_cents) / $this->compare_at_price_cents) * 100
        );
    }

    public function getPriceFormattedAttribute(): string
    {
        return Money::format($this->price_cents);
    }

    public function getCompareAtFormattedAttribute(): ?string
    {
        return $this->compare_at_price_cents ? Money::format($this->compare_at_price_cents) : null;
    }

    /** Gross margin in cents, or null when no cost has been recorded. */
    public function getMarginCentsAttribute(): ?int
    {
        return $this->cost_cents === null ? null : $this->price_cents - $this->cost_cents;
    }

    /* ---- Form inputs -------------------------------------------------
       Unsymboled, editable strings ("19.99") for the admin variant editor.
       ProductController::syncVariants() parses them back with Money::parse(),
       so the view never has to format money itself. */

    public function getPriceInputAttribute(): string
    {
        return Money::format($this->price_cents, false);
    }

    public function getCompareAtInputAttribute(): string
    {
        return $this->compare_at_price_cents !== null
            ? Money::format($this->compare_at_price_cents, false)
            : '';
    }

    public function getCostInputAttribute(): string
    {
        return $this->cost_cents !== null ? Money::format($this->cost_cents, false) : '';
    }

    /** The option values as an ordered array, for the storefront picker. */
    public function getOptionValuesAttribute(): array
    {
        return array_values(array_filter([
            $this->option1_value,
            $this->option2_value,
            $this->option3_value,
        ]));
    }
}
