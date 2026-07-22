<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One message in a ticket thread: staff reply, customer reply, or internal note. */
class TicketReply extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_internal' => 'boolean'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function scopePublic($query)
    {
        return $query->where('is_internal', false);
    }

    public function getAuthorLabelAttribute(): string
    {
        if ($this->author_name) {
            return $this->author_name;
        }

        return match ($this->author_type) {
            'customer' => $this->customer?->name ?? 'Customer',
            'staff' => $this->user?->name ?? 'Staff',
            default => 'System',
        };
    }

    public function getIsStaffAttribute(): bool
    {
        return $this->author_type === 'staff';
    }
}
