<?php

namespace App\Models\Concerns;

use App\Models\Activity;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Gives a service-desk model a polymorphic timeline. Mirrors Order::recordEvent
 * but shared across ServiceRequest, Ticket, WorkOrder and Project so we don't
 * repeat a near-identical events table + relation per model.
 */
trait RecordsActivity
{
    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject')->latest();
    }

    /** Append a timeline entry. Every status change should write one. */
    public function recordActivity(string $type, string $message, array $meta = [], ?int $userId = null, ?string $actorName = null): Activity
    {
        return $this->activities()->create([
            'type' => $type,
            'message' => $message,
            'meta' => $meta ?: null,
            'user_id' => $userId ?? auth()->id(),
            'actor_name' => $actorName,
        ]);
    }
}
