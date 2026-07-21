<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A merchant's replacement for one shipped Blade template.
 *
 * The row is the source of truth; the file under storage/app/templates is only
 * a materialised copy, because Blade's compiler needs something with a path and
 * an mtime to stat. Deleting the row (Reset To Shipped Default) is enough to
 * put the storefront back on the release's own template.
 */
class TemplateOverride extends Model
{
    protected $fillable = ['view', 'source', 'updated_by'];

    public function versions(): HasMany
    {
        return $this->hasMany(TemplateOverrideVersion::class)->latest('id');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
