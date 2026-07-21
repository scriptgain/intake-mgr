<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TaxRule extends Model
{
    use \App\Models\Concerns\Auditable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'applies_to_shipping' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Does this rule cover the given address? */
    public function covers(?string $country, ?string $state = null, ?string $postcode = null): bool
    {
        if (strtoupper((string) $country) !== strtoupper((string) $this->country)) {
            return false;
        }

        if ($this->state && strtoupper((string) $state) !== strtoupper($this->state)) {
            return false;
        }

        // A postcode on the rule narrows it further; a prefix match lets a rule
        // cover "852*" style ranges without one row per ZIP.
        if ($this->postcode && ! str_starts_with((string) $postcode, rtrim($this->postcode, '*'))) {
            return false;
        }

        return true;
    }

    /**
     * The winning rule for an address and product tax class. Most specific
     * (highest priority, then postcode > state > country) wins.
     */
    public static function resolve(?string $country, ?string $state, ?string $postcode, string $taxClass = 'standard'): ?self
    {
        return static::active()
            ->where('tax_class', $taxClass)
            ->orderByDesc('priority')
            ->orderByRaw('CASE WHEN postcode IS NOT NULL THEN 0 WHEN state IS NOT NULL THEN 1 ELSE 2 END')
            ->get()
            ->first(fn (self $rule) => $rule->covers($country, $state, $postcode));
    }

    public function getRateLabelAttribute(): string
    {
        return Money::bpsToPercent((int) $this->rate_bps);
    }

    public function getRegionLabelAttribute(): string
    {
        return collect([$this->postcode, $this->state, $this->country])->filter()->implode(', ');
    }
}
