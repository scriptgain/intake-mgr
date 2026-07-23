<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links a work order back to the booking type it was booked through and, when it
 * came from a scheduling link, the service request it fulfils. Both nullable: a
 * work order created by staff by hand has neither.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->foreignId('booking_type_id')->nullable()->after('assigned_user_id')
                ->constrained('booking_types')->nullOnDelete();
            $table->foreignId('service_request_id')->nullable()->after('booking_type_id')
                ->constrained('service_requests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropForeign(['booking_type_id']);
            $table->dropForeign(['service_request_id']);
            $table->dropColumn(['booking_type_id', 'service_request_id']);
        });
    }
};
