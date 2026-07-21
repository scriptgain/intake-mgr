<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Discount codes.
 *
 * `value` is interpreted by `type`:
 *   percentage    -> basis points (1250 = 12.5%), so fractional percents work
 *                    without a float column
 *   fixed_amount  -> cents off the subtotal
 *   free_shipping -> value ignored
 *
 * `applies_to` narrows the eligible line items; `target_ids` holds the matching
 * collection or product ids.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('discounts')) {
            Schema::create('discounts', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('title')->nullable();
                $table->string('type')->default('percentage'); // percentage|fixed_amount|free_shipping
                $table->unsignedBigInteger('value')->default(0);

                $table->string('applies_to')->default('all'); // all|collections|products
                $table->json('target_ids')->nullable();

                $table->unsignedBigInteger('min_subtotal_cents')->nullable();
                $table->unsignedInteger('usage_limit')->nullable();          // null = unlimited
                $table->unsignedInteger('usage_limit_per_customer')->nullable();
                $table->unsignedInteger('used_count')->default(0);
                $table->boolean('once_per_customer')->default(false);

                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index(['is_active', 'starts_at', 'ends_at']);
            });
        }

        // One row per redemption, so per-customer limits are enforceable and the
        // discount's performance is reportable.
        if (! Schema::hasTable('discount_redemptions')) {
            Schema::create('discount_redemptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('discount_id')->constrained()->cascadeOnDelete();
                $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
                $table->string('email')->nullable();
                $table->unsignedBigInteger('amount_cents')->default(0);
                $table->timestamps();
                $table->index(['discount_id', 'customer_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_redemptions');
        Schema::dropIfExists('discounts');
    }
};
