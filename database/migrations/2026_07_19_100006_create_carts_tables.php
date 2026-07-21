<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Server-side carts, keyed by an opaque token held in a cookie.
 *
 * Storing the cart in the database rather than the session means it survives a
 * session expiry, follows a shopper who signs in mid-visit (the cart is claimed
 * by their customer_id), and gives the merchant an abandoned-cart list.
 *
 * `unit_price_cents` is captured on add so a mid-visit price change never
 * silently re-prices what the shopper thought they were buying; checkout
 * re-validates against the live variant price before charging.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('carts')) {
            Schema::create('carts', function (Blueprint $table) {
                $table->id();
                $table->string('token', 64)->unique();
                $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
                $table->string('email')->nullable();
                $table->string('discount_code')->nullable();
                // Set once the cart converts, so abandoned-cart queries can
                // exclude it without deleting the row.
                $table->foreignId('order_id')->nullable();
                $table->timestamp('converted_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
                $table->index('expires_at');
                $table->index('converted_at');
            });
        }

        if (! Schema::hasTable('cart_items')) {
            Schema::create('cart_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
                $table->unsignedInteger('quantity')->default(1);
                $table->unsignedBigInteger('unit_price_cents')->default(0);
                $table->timestamps();
                $table->unique(['cart_id', 'product_variant_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
    }
};
