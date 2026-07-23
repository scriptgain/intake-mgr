<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A line on a quote: a service estimated, with a frozen name and price. */
class QuoteItem extends Model
{
    protected $guarded = ['id'];

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'service_id');
    }

    public function getTotalFormattedAttribute(): string
    {
        return Money::format($this->total_cents);
    }

    public function getUnitPriceFormattedAttribute(): string
    {
        return Money::format($this->unit_price_cents);
    }
}
