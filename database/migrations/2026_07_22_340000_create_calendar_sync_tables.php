<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Two-way sync bookkeeping.
 *
 * calendar_synced_events maps a work order to the remote event it created on a
 * connection, so a reschedule updates that event and a cancel/delete removes it.
 * calendar_busy_blocks caches external busy times pulled from a connection, used
 * to compute a technician's real free/busy for availability.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('calendar_synced_events')) {
            Schema::create('calendar_synced_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('calendar_connection_id')->constrained()->cascadeOnDelete();
                $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
                $table->string('remote_event_id');
                $table->string('etag')->nullable();
                $table->timestamp('last_pushed_at')->nullable();
                $table->timestamps();

                $table->unique(['calendar_connection_id', 'work_order_id'], 'cal_synced_conn_wo_unique');
            });
        }

        if (! Schema::hasTable('calendar_busy_blocks')) {
            Schema::create('calendar_busy_blocks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('calendar_connection_id')->constrained()->cascadeOnDelete();
                $table->timestamp('starts_at');
                $table->timestamp('ends_at');
                $table->string('remote_event_id')->nullable();
                $table->timestamps();

                $table->index(['calendar_connection_id', 'starts_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_busy_blocks');
        Schema::dropIfExists('calendar_synced_events');
    }
};
