<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Per-staff timezone (availability + calendar events localize to it; storage
 * stays UTC) and a secret token for that staff member's subscribe-able calendar
 * feed URL (calendar/feed/{token}.ics).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (! Schema::hasColumn('users', 'timezone')) {
                    $table->string('timezone')->nullable();
                }
                if (! Schema::hasColumn('users', 'calendar_feed_token')) {
                    $table->string('calendar_feed_token', 64)->nullable()->unique();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                foreach (['timezone', 'calendar_feed_token'] as $col) {
                    if (Schema::hasColumn('users', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
