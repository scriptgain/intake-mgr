<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One point in a template's edit history.
 *
 * Append-only: a revert does not rewind history, it writes a new version whose
 * source is the older one. That way the log always reads forward and a revert
 * is itself revertable.
 */
class TemplateOverrideVersion extends Model
{
    protected $fillable = ['template_override_id', 'view', 'source', 'action', 'note', 'user_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Badge colour for this version's action, used by the history table. */
    public function tone(): string
    {
        return [
            'save' => 'info',
            'revert' => 'warn',
            'reset' => 'danger',
            'import' => 'neutral',
        ][$this->action] ?? 'neutral';
    }

    public function label(): string
    {
        return [
            'save' => 'Saved',
            'revert' => 'Reverted',
            'reset' => 'Reset To Default',
            'import' => 'Imported',
        ][$this->action] ?? ucfirst((string) $this->action);
    }
}
