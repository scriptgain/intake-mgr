<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Support\Money;
use Illuminate\Support\Str;

class Product extends Model
{
    use \App\Models\Concerns\Auditable;
    use SoftDeletes;

    public const STATUSES = ['draft', 'active', 'archived'];

    protected $fillable = [
        'name', 'slug', 'excerpt', 'description', 'status', 'vendor', 'product_type',
        'tax_class', 'requires_shipping', 'is_featured', 'seo_title', 'seo_description', 'position',
        // Per-entity SEO. meta_* supersede the legacy seo_* pair, which is kept
        // as a read fallback so existing copy is never orphaned.
        'meta_title', 'meta_description', 'og_image', 'canonical_url', 'noindex',
    ];

    protected function casts(): array
    {
        return [
            'requires_shipping' => 'boolean',
            'is_featured' => 'boolean',
            'noindex' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // A product without a slug is unreachable on the storefront, so fill one
        // in rather than letting a merchant save a broken row.
        static::saving(function (Product $product) {
            if (blank($product->slug)) {
                $product->slug = static::uniqueSlug($product->name, $product->id);
            }
        });
    }

    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'product';
        $slug = $base;
        $i = 2;

        while (static::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('position')->orderBy('id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('position')->orderBy('id');
    }

    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(Collection::class)->withPivot('position');
    }

    /** The variant that carries the headline price when no option is chosen. */
    public function defaultVariant(): ?ProductVariant
    {
        return $this->variants->firstWhere('is_default', true) ?? $this->variants->first();
    }

    public function primaryImage(): ?ProductImage
    {
        return $this->images->first();
    }

    /* ---- Storefront scopes ------------------------------------------- */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /** Products a shopper may actually see and buy. */
    public function scopeStorefront(Builder $query): Builder
    {
        $query->active();

        if (config('shop.hide_out_of_stock')) {
            $query->whereHas('variants', function (Builder $q) {
                $q->where('track_inventory', false)->orWhere('inventory_qty', '>', 0);
            });
        }

        return $query;
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('vendor', 'like', "%{$term}%")
                ->orWhere('product_type', 'like', "%{$term}%")
                ->orWhereHas('variants', fn (Builder $v) => $v->where('sku', 'like', "%{$term}%"));
        });
    }

    /* ---- Derived attributes (view-ready, no logic in Blade) ----------- */

    /** Lowest active variant price in cents. */
    public function getPriceFromCentsAttribute(): int
    {
        return (int) ($this->variants->min('price_cents') ?? 0);
    }

    public function getPriceToCentsAttribute(): int
    {
        return (int) ($this->variants->max('price_cents') ?? 0);
    }

    public function getPriceFromFormattedAttribute(): string
    {
        return Money::format($this->price_from_cents);
    }

    public function getPriceToFormattedAttribute(): string
    {
        return Money::format($this->price_to_cents);
    }

    public function getHasPriceRangeAttribute(): bool
    {
        return $this->price_from_cents !== $this->price_to_cents;
    }

    /** True when any variant has a compare-at price above its sale price. */
    public function getIsOnSaleAttribute(): bool
    {
        return $this->variants->contains(fn (ProductVariant $v) => $v->is_on_sale);
    }

    public function getTotalInventoryAttribute(): int
    {
        return (int) $this->variants
            ->where('track_inventory', true)
            ->sum('inventory_qty');
    }

    /** Badge/rail colour for the product's status, resolved here not in a view. */
    public function getStatusBadgeAttribute(): string
    {
        return [
            'active' => 'success',
            'draft' => 'neutral',
            'archived' => 'danger',
        ][$this->status] ?? 'neutral';
    }

    /**
     * Stock tone for the inventory column: out of stock reads as a failure,
     * at or below the low-stock threshold as a warning.
     */
    public function getInventoryBadgeAttribute(): string
    {
        if (! $this->variants->contains('track_inventory', true)) {
            return 'neutral';
        }

        $total = $this->total_inventory;

        if ($total <= 0) {
            return 'danger';
        }

        return $total <= (int) config('shop.low_stock_threshold', 5) ? 'warn' : 'neutral';
    }

    public function getIsInStockAttribute(): bool
    {
        return $this->variants->contains(fn (ProductVariant $v) => $v->is_in_stock);
    }

    /**
     * The distinct option axes across this product's variants, shaped for the
     * storefront variant picker: [['name' => 'Size', 'values' => ['S','M']]].
     */
    public function getOptionAxesAttribute(): array
    {
        $axes = [];

        foreach ([1, 2, 3] as $i) {
            $name = $this->variants->pluck("option{$i}_name")->filter()->first();
            if (! $name) {
                continue;
            }
            $values = $this->variants
                ->pluck("option{$i}_value")
                ->filter()
                ->unique()
                ->values()
                ->all();

            if ($values) {
                $axes[] = ['index' => $i, 'name' => $name, 'values' => $values];
            }
        }

        return $axes;
    }
}
