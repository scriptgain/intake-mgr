<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One entry in the timeline of a service-desk subject (ServiceRequest, Ticket,
 * WorkOrder, Project). The polymorphic sibling of OrderEvent; written by the
 * RecordsActivity trait so every status change leaves a trail.
 */
class Activity extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Timeline icon, resolved here so the Blade timeline stays markup-only. */
    public function getIconAttribute(): string
    {
        return [
            'created' => 'plus',
            'status' => 'refresh',
            'assigned' => 'user',
            'scheduled' => 'clock',
            'reply' => 'envelope',
            'note' => 'edit',
            'payment' => 'credit-card',
            'converted' => 'external',
            'cancelled' => 'x-circle',
            'completed' => 'check-circle',
        ][$this->type] ?? 'info';
    }

    public function getToneAttribute(): string
    {
        return [
            'completed' => 'success',
            'payment' => 'success',
            'scheduled' => 'info',
            'cancelled' => 'danger',
            'note' => 'warn',
        ][$this->type] ?? 'neutral';
    }

    public function getActorAttribute(): string
    {
        return $this->actor_name ?: ($this->user?->name ?? 'System');
    }
}
