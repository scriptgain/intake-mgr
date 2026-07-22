<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** A named kind of appointment: duration, buffers, optional price + technician. */
class BookingType extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (BookingType $type) {
            if (blank($type->slug)) {
                $base = Str::slug($type->name) ?: 'booking-type';
                $slug = $base;
                $i = 2;
                while (static::where('slug', $slug)->where('id', '!=', $type->id)->exists()) {
                    $slug = $base.'-'.$i++;
                }
                $type->slug = $slug;
            }
        });
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(fn (Builder $q) => $q->where('name', 'like', "%{$term}%")->orWhere('description', 'like', "%{$term}%"));
    }

    public function getPriceFormattedAttribute(): string
    {
        return $this->price_cents > 0 ? Money::format($this->price_cents) : 'Free';
    }

    public function getTotalMinutesAttribute(): int
    {
        return (int) $this->buffer_before_minutes + (int) $this->duration_minutes + (int) $this->buffer_after_minutes;
    }
}
