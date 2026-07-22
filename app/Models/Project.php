<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasSequentialNumber;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * An engagement grouping related tickets and work orders (a pool remodel, a
 * seasonal contract). Status planning -> active -> completed (or on_hold /
 * cancelled).
 */
class Project extends Model
{
    use Auditable;
    use HasSequentialNumber;
    use RecordsActivity;

    public const NUMBER_PREFIX = 'PRJ-';

    public const NUMBER_START = 1001;

    public const STATUSES = ['planning', 'active', 'on_hold', 'completed', 'cancelled'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'due_on' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->latest();
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('number', 'like', "%{$term}%")
                ->orWhere('name', 'like', "%{$term}%")
                ->orWhereHas('customer', fn (Builder $c) => $c->search($term));
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', ['planning', 'active']);
    }

    public function getStatusBadgeAttribute(): string
    {
        return [
            'planning' => 'warn',
            'active' => 'info',
            'on_hold' => 'warn',
            'completed' => 'success',
            'cancelled' => 'danger',
        ][$this->status] ?? 'neutral';
    }

    public function getStatusLabelAttribute(): string
    {
        return ucwords(str_replace('_', ' ', (string) $this->status));
    }

    /** Simple progress: share of work orders completed. */
    public function getProgressPercentAttribute(): int
    {
        $total = $this->workOrders()->count();
        if ($total === 0) {
            return $this->status === 'completed' ? 100 : 0;
        }
        $done = $this->workOrders()->where('status', 'completed')->count();

        return (int) round($done / $total * 100);
    }
}
