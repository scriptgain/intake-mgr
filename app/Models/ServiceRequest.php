<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasSequentialNumber;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * The front door: a customer's incoming "Request Service" submission (or one a
 * staff member logs by phone). Triaged, then converted into a ticket and/or a
 * work order.
 */
class ServiceRequest extends Model
{
    use Auditable;
    use HasSequentialNumber;
    use RecordsActivity;

    public const NUMBER_PREFIX = 'REQ-';

    public const STATUSES = ['new', 'triaged', 'converted', 'closed'];

    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'address' => 'array',
            'closed_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'service_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
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
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('subject', 'like', "%{$term}%");
        });
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', ['new', 'triaged']);
    }

    public function getIsOpenAttribute(): bool
    {
        return in_array($this->status, ['new', 'triaged'], true);
    }

    public function getStatusBadgeAttribute(): string
    {
        return [
            'new' => 'info',
            'triaged' => 'warn',
            'converted' => 'success',
            'closed' => 'neutral',
        ][$this->status] ?? 'neutral';
    }

    public function getPriorityBadgeAttribute(): string
    {
        return [
            'low' => 'neutral',
            'normal' => 'info',
            'high' => 'warn',
            'urgent' => 'danger',
        ][$this->priority] ?? 'neutral';
    }

    public function getStatusLabelAttribute(): string
    {
        return ucwords(str_replace('_', ' ', (string) $this->status));
    }
}
