<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasSequentialNumber;
use App\Models\Concerns\RecordsActivity;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Scheduled service work. Status scheduled -> in_progress -> completed (or
 * on_hold / cancelled). Line items freeze the service name + price. Completing
 * a work order can generate an invoice (Order) linked via invoice_order_id.
 */
class WorkOrder extends Model
{
    use Auditable;
    use HasSequentialNumber;
    use RecordsActivity;

    public const NUMBER_PREFIX = 'WO-';

    public const STATUSES = ['scheduled', 'in_progress', 'on_hold', 'completed', 'cancelled'];

    /** Statuses the customer can still reschedule/cancel. */
    public const CHANGEABLE_STATUSES = ['scheduled', 'on_hold'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'address' => 'array',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(WorkOrderItem::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'invoice_order_id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->latest();
    }

    /** Recompute the money rollup from line items. */
    public function recalcTotals(): void
    {
        $subtotal = (int) $this->items()->sum('total_cents');
        $this->forceFill(['subtotal_cents' => $subtotal])->save();
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('number', 'like', "%{$term}%")
                ->orWhere('title', 'like', "%{$term}%")
                ->orWhereHas('customer', fn (Builder $c) => $c->search($term));
        });
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->whereIn('status', ['scheduled', 'in_progress'])->orderBy('scheduled_at');
    }

    public function getIsChangeableAttribute(): bool
    {
        return in_array($this->status, self::CHANGEABLE_STATUSES, true);
    }

    public function getIsBillableAttribute(): bool
    {
        return $this->status === 'completed' && $this->subtotal_cents > 0;
    }

    public function getSubtotalFormattedAttribute(): string
    {
        return Money::format($this->subtotal_cents);
    }

    public function getStatusBadgeAttribute(): string
    {
        return [
            'scheduled' => 'info',
            'in_progress' => 'info',
            'on_hold' => 'warn',
            'completed' => 'success',
            'cancelled' => 'danger',
        ][$this->status] ?? 'neutral';
    }

    public function getStatusLabelAttribute(): string
    {
        return ucwords(str_replace('_', ' ', (string) $this->status));
    }
}
