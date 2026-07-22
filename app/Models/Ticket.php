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
 * A service-desk conversation. Status lifecycle open -> pending -> in_progress
 * -> resolved -> closed, with a priority axis. Replies form the thread; internal
 * replies never reach the customer portal.
 */
class Ticket extends Model
{
    use Auditable;
    use HasSequentialNumber;
    use RecordsActivity;

    public const NUMBER_PREFIX = 'TKT-';

    public const STATUSES = ['open', 'pending', 'in_progress', 'resolved', 'closed'];

    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    /** Statuses a customer still considers "live". */
    public const OPEN_STATUSES = ['open', 'pending', 'in_progress'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'last_reply_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(TicketReply::class)->oldest();
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
                ->orWhere('subject', 'like', "%{$term}%")
                ->orWhereHas('customer', fn (Builder $c) => $c->search($term));
        });
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }

    public function getIsOpenAttribute(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function getStatusBadgeAttribute(): string
    {
        return [
            'open' => 'info',
            'pending' => 'warn',
            'in_progress' => 'info',
            'resolved' => 'success',
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

    public function getPriorityLabelAttribute(): string
    {
        return ucwords((string) $this->priority);
    }
}
