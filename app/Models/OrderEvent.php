<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One entry in an order's timeline. Written by Order::recordEvent(). */
class OrderEvent extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** The staff member who acted, or null for system-generated entries. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Timeline icon, resolved here so the Blade timeline stays markup-only. */
    public function getIconAttribute(): string
    {
        return [
            'placed' => 'bag',
            'paid' => 'credit-card',
            'fulfilled' => 'truck',
            'refunded' => 'refresh',
            'cancelled' => 'x-circle',
            'note' => 'edit',
            'email' => 'envelope',
        ][$this->type] ?? 'info';
    }

    public function getToneAttribute(): string
    {
        return [
            'paid' => 'success',
            'fulfilled' => 'success',
            'refunded' => 'warn',
            'cancelled' => 'danger',
        ][$this->type] ?? 'neutral';
    }

    public function getActorAttribute(): string
    {
        return $this->user?->name ?? 'System';
    }
}
