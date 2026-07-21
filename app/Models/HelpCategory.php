<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class HelpCategory extends Model
{
    use \App\Models\Concerns\Auditable;

    protected $fillable = [
        'name', 'slug', 'description', 'icon', 'is_published', 'position',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (HelpCategory $category) {
            if (blank($category->slug)) {
                $category->slug = static::uniqueSlug($category->name, $category->id);
            }
        });
    }

    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $i = 2;

        while (static::where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    /**
     * Named `articles` (not `helpArticles`) so scoped route binding on
     * /help/{category:slug}/{article:slug} resolves the child through it.
     */
    public function articles(): HasMany
    {
        return $this->hasMany(HelpArticle::class)->orderBy('position')->orderBy('title');
    }

    public function publishedArticles(): HasMany
    {
        return $this->articles()->where('is_published', true);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }
}
