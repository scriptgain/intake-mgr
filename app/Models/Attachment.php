<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

/**
 * A file hung off any service-desk subject (a photo of the leak, a signed
 * estimate). Only metadata + a private-disk path is stored; the bytes live on
 * the disk and stream through a gated download route.
 */
class Attachment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_internal' => 'boolean'];
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploadedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function uploadedByCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'uploaded_by_customer_id');
    }

    public function getIsImageAttribute(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }

    public function getSizeFormattedAttribute(): string
    {
        $bytes = (int) $this->size;
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        $units = ['KB', 'MB', 'GB'];
        $i = (int) floor(log($bytes, 1024)) - 1;
        $i = max(0, min($i, count($units) - 1));

        return round($bytes / (1024 ** ($i + 1)), 1).' '.$units[$i];
    }

    /** Best-effort delete of the underlying file when the record goes. */
    protected static function booted(): void
    {
        static::deleting(function (Attachment $a) {
            try {
                Storage::disk($a->disk ?: 'local')->delete($a->path);
            } catch (\Throwable $e) {
                // The row still goes; a stray file is harmless.
            }
        });
    }
}
