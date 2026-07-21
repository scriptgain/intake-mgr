<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    protected $fillable = ['product_id', 'product_variant_id', 'path', 'alt', 'position'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * Public URL for the stored file. Uploads live on the `public` disk, so a
     * fresh install needs `php artisan storage:link` (the installer runs it).
     */
    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    public function getAltTextAttribute(): string
    {
        return $this->alt ?: ($this->product?->name ?? 'Product Image');
    }
}
