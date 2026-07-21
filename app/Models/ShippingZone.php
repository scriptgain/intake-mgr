<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingZone extends Model
{
    use \App\Models\Concerns\Auditable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'countries' => 'array',
            'states' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function rates(): HasMany
    {
        return $this->hasMany(ShippingRate::class)->orderBy('position')->orderBy('id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Does this zone cover the given address? */
    public function covers(?string $country, ?string $state = null): bool
    {
        $countries = $this->countries ?? [];
        $matchesCountry = in_array('*', $countries, true)
            || in_array(strtoupper((string) $country), array_map('strtoupper', $countries), true);

        if (! $matchesCountry) {
            return false;
        }

        // No state list means the whole country.
        if (blank($this->states)) {
            return true;
        }

        return in_array(strtoupper((string) $state), array_map('strtoupper', $this->states), true);
    }

    /**
     * The zone serving an address. Specific zones are declared before the
     * catch-all by `position`, so the first match is the most specific one.
     */
    public static function forAddress(?string $country, ?string $state = null): ?self
    {
        return static::active()
            ->with('rates')
            ->orderBy('position')
            ->get()
            ->first(fn (self $zone) => $zone->covers($country, $state));
    }

    public function getCountryLabelAttribute(): string
    {
        $countries = $this->countries ?? [];

        if (in_array('*', $countries, true)) {
            return 'Rest Of World';
        }

        return count($countries) > 4
            ? count($countries).' Countries'
            : implode(', ', $countries);
    }
}
