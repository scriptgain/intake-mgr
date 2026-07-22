<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Maps a work order to the remote calendar event it created on a connection. */
class CalendarSyncedEvent extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['last_pushed_at' => 'datetime'];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(CalendarConnection::class, 'calendar_connection_id');
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }
}
