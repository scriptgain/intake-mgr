<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class HelpArticle extends Model
{
    use \App\Models\Concerns\Auditable;

    protected $fillable = [
        'help_category_id', 'title', 'slug', 'excerpt', 'body',
        'is_published', 'position', 'views',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (HelpArticle $article) {
            if (blank($article->slug)) {
                $article->slug = static::uniqueSlug($article->title, $article->id);
            }
        });
    }

    public static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'article';
        $slug = $base;
        $i = 2;

        while (static::where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(HelpCategory::class, 'help_category_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /** Body is authored as Markdown; render it server-side for display. */
    public function getBodyHtmlAttribute(): string
    {
        return $this->body ? Str::markdown($this->body) : '';
    }
}
