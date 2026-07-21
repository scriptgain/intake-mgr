<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Storefront customers. Deliberately NOT the `users` table: `users` are staff
 * who sign into the merchant admin, customers sign into the shop. They get
 * their own auth guard (config/auth.php -> 'customer') so a customer can never
 * hold an admin session and vice versa.
 *
 * `password` is nullable — guest checkout creates a customer record with no
 * credentials, and the shopper can claim the account later.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customers')) {
            Schema::create('customers', function (Blueprint $table) {
                $table->id();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('email')->unique();
                $table->string('phone')->nullable();
                $table->string('password')->nullable();
                $table->rememberToken();
                $table->boolean('accepts_marketing')->default(false);
                // Merchant-only notes shown on the customer detail page.
                $table->text('notes')->nullable();
                // Last-used addresses, prefilled at checkout.
                $table->json('default_shipping_address')->nullable();
                $table->json('default_billing_address')->nullable();
                // Rolled up on each paid order so the customers list can sort
                // by value without aggregating orders on every page load.
                $table->unsignedInteger('orders_count')->default(0);
                $table->unsignedBigInteger('total_spent_cents')->default(0);
                $table->timestamp('last_order_at')->nullable();
                $table->timestamp('email_verified_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index('last_order_at');
            });
        }

        if (! Schema::hasTable('customer_addresses')) {
            Schema::create('customer_addresses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->string('label')->nullable(); // "Home", "Work"
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('company')->nullable();
                $table->string('line1');
                $table->string('line2')->nullable();
                $table->string('city');
                $table->string('state')->nullable();
                $table->string('postcode')->nullable();
                $table->string('country', 2)->default('US');
                $table->string('phone')->nullable();
                $table->boolean('is_default')->default(false);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
        Schema::dropIfExists('customers');
    }
};
