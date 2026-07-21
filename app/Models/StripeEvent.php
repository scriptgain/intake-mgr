<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per Stripe webhook event we have ever accepted.
 *
 * This table exists for exactly one reason: Stripe redelivers. Any non-2xx
 * response, any timeout, any network blip and the same event arrives again,
 * sometimes hours later. Without a unique record of event_id, a redelivered
 * payment_intent.succeeded would post a second payment against the same order.
 *
 * The event PAYLOAD is deliberately not stored. Stripe event bodies contain
 * billing details and, on some event types, enough card metadata that keeping
 * them turns an application database into a compliance problem for no benefit.
 * The id is enough: anything else can be re-fetched from Stripe by id.
 */
class StripeEvent extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'livemode' => 'boolean',
            'processed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function markProcessed(?string $note = null): void
    {
        $this->forceFill([
            'status' => 'processed',
            'note' => $note,
            'processed_at' => now(),
        ])->save();
    }

    public function markIgnored(string $note): void
    {
        $this->forceFill([
            'status' => 'ignored',
            'note' => $note,
            'processed_at' => now(),
        ])->save();
    }

    public function markFailed(string $note): void
    {
        $this->forceFill([
            'status' => 'failed',
            'note' => substr($note, 0, 255),
            'processed_at' => now(),
        ])->save();
    }
}
