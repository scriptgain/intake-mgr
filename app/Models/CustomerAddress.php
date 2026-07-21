<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerAddress extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** Flatten to the array shape orders store in shipping_address/billing_address. */
    public function toOrderAddress(): array
    {
        return $this->only([
            'first_name', 'last_name', 'company', 'line1', 'line2',
            'city', 'state', 'postcode', 'country', 'phone',
        ]);
    }

    /** One-line rendering for lists. */
    public function getSummaryAttribute(): string
    {
        return collect([$this->line1, $this->line2, $this->city, $this->state, $this->postcode])
            ->filter()
            ->implode(', ');
    }
}
