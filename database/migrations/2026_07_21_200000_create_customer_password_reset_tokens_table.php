<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A dedicated reset-token store for the storefront 'customer' guard.
 *
 * Deliberately separate from the staff 'password_reset_tokens' table: a shopper
 * and an admin can register the same email on the two guards, and a shared,
 * email-keyed token table would let one guard's reset collide with the other's.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customer_password_reset_tokens')) {
            return;
        }

        Schema::create('customer_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_password_reset_tokens');
    }
};
