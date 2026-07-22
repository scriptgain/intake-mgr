<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Booking types are the named kinds of appointment a business offers (e.g.
 * "Standard Service Call", "Emergency Callout"), each with a duration, buffers,
 * an optional price and an optional default technician. They drive scheduling
 * and availability slot generation.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('booking_types')) {
            Schema::create('booking_types', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->unsignedInteger('duration_minutes')->default(60);
                $table->unsignedInteger('buffer_before_minutes')->default(0);
                $table->unsignedInteger('buffer_after_minutes')->default(0);
                $table->unsignedBigInteger('price_cents')->default(0);
                // Null = any available technician.
                $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('color', 16)->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('position')->default(0);
                $table->timestamps();

                $table->index(['is_active', 'position']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_types');
    }
};
