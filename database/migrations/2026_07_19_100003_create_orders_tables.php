<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Orders, their line items, and their timeline.
 *
 * Three independent status axes, matching how real commerce works — an order
 * can be paid but unfulfilled, or fulfilled but refunded:
 *   status            open | cancelled | archived      (the order's lifecycle)
 *   financial_status  pending | paid | partially_refunded | refunded | failed
 *   fulfillment_status unfulfilled | partially_fulfilled | fulfilled | returned
 *
 * Line items denormalise the product name, variant label, SKU, and unit price
 * at time of sale. Renaming or deleting a product later must never rewrite what
 * a customer was actually charged.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            Schema::create('orders', function (Blueprint $table) {
                $table->id();
                $table->string('number')->unique(); // SM-1042
                $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
                $table->string('email');
                $table->string('phone')->nullable();

                $table->string('status')->default('open');
                $table->string('financial_status')->default('pending');
                $table->string('fulfillment_status')->default('unfulfilled');

                $table->string('currency', 3)->default('USD');
                $table->unsignedBigInteger('subtotal_cents')->default(0);
                $table->unsignedBigInteger('discount_cents')->default(0);
                $table->unsignedBigInteger('shipping_cents')->default(0);
                $table->unsignedBigInteger('tax_cents')->default(0);
                $table->unsignedBigInteger('total_cents')->default(0);
                $table->unsignedBigInteger('refunded_cents')->default(0);

                $table->foreignId('discount_id')->nullable();
                $table->string('discount_code')->nullable();

                $table->json('shipping_address')->nullable();
                $table->json('billing_address')->nullable();
                $table->string('shipping_method')->nullable();

                $table->string('payment_gateway')->nullable(); // manual|stripe
                $table->string('payment_reference')->nullable();
                $table->timestamp('paid_at')->nullable();

                // Customer-visible note + merchant-only note.
                $table->text('customer_note')->nullable();
                $table->text('staff_note')->nullable();

                $table->timestamp('cancelled_at')->nullable();
                $table->string('cancel_reason')->nullable();
                $table->timestamps();

                $table->index(['status', 'created_at']);
                $table->index('financial_status');
                $table->index('fulfillment_status');
                $table->index('email');
            });
        }

        if (! Schema::hasTable('order_items')) {
            Schema::create('order_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained()->cascadeOnDelete();
                // Nulled rather than cascaded: deleting a product must not
                // delete the history of it having been sold.
                $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();

                // Frozen at time of sale.
                $table->string('name');
                $table->string('variant_name')->nullable();
                $table->string('sku')->nullable();
                $table->string('image_path')->nullable();

                $table->unsignedInteger('quantity')->default(1);
                $table->unsignedBigInteger('unit_price_cents')->default(0);
                $table->unsignedBigInteger('discount_cents')->default(0);
                $table->unsignedBigInteger('tax_cents')->default(0);
                $table->unsignedBigInteger('total_cents')->default(0);
                $table->unsignedInteger('fulfilled_qty')->default(0);
                $table->boolean('requires_shipping')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('order_events')) {
            Schema::create('order_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained()->cascadeOnDelete();
                // Null when the system (not a staff member) wrote the entry.
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('type'); // placed|paid|fulfilled|refunded|cancelled|note|email
                $table->string('message');
                $table->json('meta')->nullable();
                $table->timestamps();
                $table->index(['order_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('fulfillments')) {
            Schema::create('fulfillments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('status')->default('pending'); // pending|shipped|delivered|cancelled
                $table->string('carrier')->nullable();
                $table->string('tracking_number')->nullable();
                $table->string('tracking_url')->nullable();
                // [{order_item_id, quantity}, ...] — a partial shipment.
                $table->json('items')->nullable();
                $table->boolean('notify_customer')->default(true);
                $table->timestamp('shipped_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fulfillments');
        Schema::dropIfExists('order_events');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
