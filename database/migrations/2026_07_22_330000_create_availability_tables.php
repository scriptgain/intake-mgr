<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Per-staff availability. availability_rules are the recurring weekly working
 * hours (a technician can have several blocks per weekday). availability_exceptions
 * are date-specific overrides: a day off (is_available=false) or special hours.
 * Times are stored in the staff member's timezone (users.timezone).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('availability_rules')) {
            Schema::create('availability_rules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->unsignedTinyInteger('weekday'); // 0=Sun .. 6=Sat
                $table->time('start_time');
                $table->time('end_time');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['user_id', 'weekday']);
            });
        }

        if (! Schema::hasTable('availability_exceptions')) {
            Schema::create('availability_exceptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->date('date');
                $table->boolean('is_available')->default(false); // false = time off
                $table->time('start_time')->nullable();
                $table->time('end_time')->nullable();
                $table->string('reason')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_exceptions');
        Schema::dropIfExists('availability_rules');
    }
};
