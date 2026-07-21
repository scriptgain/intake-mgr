<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class StorePage extends Model
{
    use \App\Models\Concerns\Auditable;

    protected $fillable = [
        'title', 'slug', 'body', 'is_published', 'position',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    /** Reused by the audit trait as the human label. */
    public function getNameAttribute(): string
    {
        return $this->title;
    }

    protected static function booted(): void
    {
        static::saving(function (StorePage $page) {
            if (blank($page->slug)) {
                $page->slug = static::uniqueSlug($page->title, $page->id);
            }
        });
    }

    public static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'page';
        $slug = $base;
        $i = 2;

        while (static::where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
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
