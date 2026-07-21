<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A shipment against an order. `items` holds [{order_item_id, quantity}] so a
 * merchant can ship part of an order and still track the rest.
 */
class Fulfillment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'notify_customer' => 'boolean',
            'shipped_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getStatusBadgeAttribute(): string
    {
        return [
            'shipped' => 'info',
            'delivered' => 'success',
            'cancelled' => 'danger',
        ][$this->status] ?? 'neutral';
    }

    /** Total units in this shipment. */
    public function getQuantityAttribute(): int
    {
        return (int) collect($this->items ?? [])->sum('quantity');
    }

    /**
     * Best-effort tracking link. An explicit tracking_url always wins; otherwise
     * a known carrier gets its standard lookup URL.
     */
    public function getTrackingLinkAttribute(): ?string
    {
        if ($this->tracking_url) {
            return $this->tracking_url;
        }

        if (! $this->tracking_number) {
            return null;
        }

        $templates = [
            'ups' => 'https://www.ups.com/track?tracknum=',
            'usps' => 'https://tools.usps.com/go/TrackConfirmAction?tLabels=',
            'fedex' => 'https://www.fedex.com/fedextrack/?trknbr=',
            'dhl' => 'https://www.dhl.com/en/express/tracking.html?AWB=',
        ];

        $key = strtolower((string) $this->carrier);

        return isset($templates[$key]) ? $templates[$key].$this->tracking_number : null;
    }
}
