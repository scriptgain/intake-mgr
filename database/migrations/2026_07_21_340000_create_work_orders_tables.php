<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Work orders are scheduled service work. One lifecycle axis:
 *   status  scheduled | in_progress | on_hold | completed | cancelled
 * Line items denormalise the service name and unit price at time of scheduling,
 * so retiring or repricing a service never rewrites what was quoted. Completing
 * a work order can generate an invoice (an Order) linked back via order.work_order_id.
 * The customer can reschedule/cancel from the portal (writes a timeline entry).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('work_orders')) {
            Schema::create('work_orders', function (Blueprint $table) {
                $table->id();
                $table->string('number')->unique(); // WO-1001
                $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();

                $table->string('title');
                $table->text('notes')->nullable();
                $table->string('status')->default('scheduled');
                $table->json('address')->nullable();

                $table->timestamp('scheduled_at')->nullable();
                $table->unsignedInteger('duration_minutes')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->string('cancel_reason')->nullable();

                // Money rolled up from items, in cents.
                $table->unsignedBigInteger('subtotal_cents')->default(0);
                $table->string('currency', 3)->default('USD');
                // Set once an invoice (Order) is generated from this work order.
                $table->foreignId('invoice_order_id')->nullable();
                $table->timestamps();

                $table->index(['status', 'scheduled_at']);
                $table->index('customer_id');
            });
        }

        if (! Schema::hasTable('work_order_items')) {
            Schema::create('work_order_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
                $table->foreignId('service_id')->nullable()->constrained('products')->nullOnDelete();
                $table->string('name'); // frozen service name
                $table->text('description')->nullable();
                $table->unsignedInteger('quantity')->default(1);
                $table->unsignedBigInteger('unit_price_cents')->default(0);
                $table->unsignedBigInteger('total_cents')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_items');
        Schema::dropIfExists('work_orders');
    }
};
