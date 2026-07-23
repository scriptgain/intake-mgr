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
use Illuminate\Support\Str;

/**
 * A priced estimate sent to a customer. draft -> sent -> accepted | declined |
 * expired -> converted. Line items freeze the service name + price. Accepting
 * (by staff or the customer) can auto-generate the invoice; staff convert an
 * accepted quote into an invoice (Order) and/or a work order.
 */
class Quote extends Model
{
    use Auditable;
    use HasSequentialNumber;
    use RecordsActivity;

    public const NUMBER_PREFIX = 'QT-';

    public const STATUSES = ['draft', 'sent', 'accepted', 'declined', 'expired', 'converted'];

    /** Statuses from which the customer/staff can still accept or decline. */
    public const ACTIONABLE_STATUSES = ['sent'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'address' => 'array',
            'valid_until' => 'date',
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
            'declined_at' => 'datetime',
            'converted_at' => 'datetime',
        ];
    }

    /** Give every new quote an unguessable token for its public accept link. */
    protected static function booted(): void
    {
        static::creating(function (Quote $quote) {
            if (! $quote->accept_token) {
                $quote->accept_token = Str::random(40);
            }
        });
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'invoice_order_id');
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'work_order_id');
    }

    /** Recompute the money rollup: total = subtotal - discount + tax. */
    public function recalcTotals(): void
    {
        $subtotal = (int) $this->items()->sum('total_cents');
        $total = max(0, $subtotal - (int) $this->discount_cents + (int) $this->tax_cents);

        $this->forceFill([
            'subtotal_cents' => $subtotal,
            'total_cents' => $total,
        ])->save();
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

    public function getIsActionableAttribute(): bool
    {
        return in_array($this->status, self::ACTIONABLE_STATUSES, true);
    }

    public function getIsConvertibleAttribute(): bool
    {
        return $this->status === 'accepted';
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->valid_until
            && $this->valid_until->isPast()
            && in_array($this->status, ['sent', 'draft'], true);
    }

    public function getSubtotalFormattedAttribute(): string
    {
        return Money::format($this->subtotal_cents);
    }

    public function getDiscountFormattedAttribute(): string
    {
        return Money::format($this->discount_cents);
    }

    public function getTaxFormattedAttribute(): string
    {
        return Money::format($this->tax_cents);
    }

    public function getTotalFormattedAttribute(): string
    {
        return Money::format($this->total_cents);
    }

    public function getStatusBadgeAttribute(): string
    {
        return [
            'draft' => 'neutral',
            'sent' => 'info',
            'accepted' => 'success',
            'declined' => 'danger',
            'expired' => 'warn',
            'converted' => 'success',
        ][$this->status] ?? 'neutral';
    }

    public function getStatusLabelAttribute(): string
    {
        return ucwords(str_replace('_', ' ', (string) $this->status));
    }
}
