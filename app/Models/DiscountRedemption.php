<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One use of a discount code, so per-customer limits are enforceable. */
class DiscountRedemption extends Model
{
    protected $guarded = ['id'];

    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
