<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Quotes (estimates). A priced proposal sent to a customer before the work is
 * won. Lifecycle:
 *   draft -> sent -> accepted | declined | expired -> converted
 * Line items freeze the service name + price the same way work orders do, so
 * repricing a service never rewrites what was quoted. An accepted quote is
 * converted by staff into an invoice (Order) and/or a work order, linked back
 * via invoice_order_id / work_order_id. accept_token backs a public accept link
 * so an emailed quote can be accepted without an account.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('quotes')) {
            Schema::create('quotes', function (Blueprint $table) {
                $table->id();
                $table->string('number')->unique(); // QT-1001
                $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('service_request_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

                $table->string('title');
                $table->text('message')->nullable();  // shown to the customer
                $table->string('status')->default('draft');
                $table->json('address')->nullable();
                $table->date('valid_until')->nullable();

                // Money rolled up from items, in cents.
                $table->unsignedBigInteger('subtotal_cents')->default(0);
                $table->unsignedBigInteger('discount_cents')->default(0);
                $table->unsignedBigInteger('tax_cents')->default(0);
                $table->unsignedBigInteger('total_cents')->default(0);
                $table->string('currency', 3)->default('USD');

                $table->string('accept_token', 64)->unique();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('accepted_at')->nullable();
                $table->timestamp('declined_at')->nullable();
                $table->timestamp('converted_at')->nullable();
                $table->string('decline_reason')->nullable();

                // Set on convert.
                $table->foreignId('invoice_order_id')->nullable();
                $table->foreignId('work_order_id')->nullable();
                $table->timestamps();

                $table->index(['status', 'valid_until']);
                $table->index('customer_id');
            });
        }

        if (! Schema::hasTable('quote_items')) {
            Schema::create('quote_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
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
        Schema::dropIfExists('quote_items');
        Schema::dropIfExists('quotes');
    }
};
