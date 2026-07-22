<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Orders are reused as invoices in IntakeMGR: the billable/payment object a
 * customer pays for a service. Link an invoice back to the work order and/or
 * project it settles. Nullable + no FK constraint (soft link) so deleting a
 * work order never cascades into paid financial history.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (! Schema::hasColumn('orders', 'work_order_id')) {
                    $table->foreignId('work_order_id')->nullable()->index();
                }
                if (! Schema::hasColumn('orders', 'project_id')) {
                    $table->foreignId('project_id')->nullable()->index();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (Schema::hasColumn('orders', 'work_order_id')) {
                    $table->dropColumn('work_order_id');
                }
                if (Schema::hasColumn('orders', 'project_id')) {
                    $table->dropColumn('project_id');
                }
            });
        }
    }
};
