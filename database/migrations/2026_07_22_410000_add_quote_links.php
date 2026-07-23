<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Link an invoice (Order) and a work order back to the quote they came from,
 * mirroring the existing work_order_id link on orders.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'quote_id')) {
                $table->foreignId('quote_id')->nullable()->after('work_order_id');
            }
        });

        Schema::table('work_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('work_orders', 'quote_id')) {
                $table->foreignId('quote_id')->nullable()->after('project_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'quote_id')) {
                $table->dropColumn('quote_id');
            }
        });

        Schema::table('work_orders', function (Blueprint $table) {
            if (Schema::hasColumn('work_orders', 'quote_id')) {
                $table->dropColumn('quote_id');
            }
        });
    }
};
