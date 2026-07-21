<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Collection extends Model
{
    use \App\Models\Concerns\Auditable;

    protected $fillable = [
        'name', 'slug', 'description', 'image_path', 'is_active', 'position',
        'seo_title', 'seo_description',
        'meta_title', 'meta_description', 'og_image', 'canonical_url', 'noindex',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'noindex' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Collection $collection) {
            if (blank($collection->slug)) {
                $collection->slug = static::uniqueSlug($collection->name, $collection->id);
            }
        });
    }

    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'collection';
        $slug = $base;
        $i = 2;

        while (static::where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)->withPivot('position');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }
}
