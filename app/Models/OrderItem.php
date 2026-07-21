<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class OrderItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['requires_shipping' => 'boolean'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** May be null: the product can be deleted long after the sale. */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function getUnitPriceFormattedAttribute(): string
    {
        return Money::format($this->unit_price_cents);
    }

    public function getTotalFormattedAttribute(): string
    {
        return Money::format($this->total_cents);
    }

    public function getUnfulfilledQtyAttribute(): int
    {
        return max(0, $this->quantity - $this->fulfilled_qty);
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    /** "Medium / Charcoal" — hidden when the product had no options. */
    public function getVariantLabelAttribute(): ?string
    {
        return ($this->variant_name && $this->variant_name !== 'Default') ? $this->variant_name : null;
    }
}
