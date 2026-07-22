<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Multi-provider payments. Stripe was the only gateway; IntakeMGR adds
 * Authorize.Net behind a gateway abstraction. Provider-neutral columns sit
 * alongside the existing stripe_* ones (left untouched so the proven Stripe
 * path is unchanged):
 *   orders.payment_provider      stripe | authorizenet | manual
 *   orders.authnet_transaction_id  Authorize.Net's charge id
 * The webhook ledger (stripe_events) gains a provider column and is reused for
 * both streams.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (! Schema::hasColumn('orders', 'payment_provider')) {
                    $table->string('payment_provider', 32)->nullable()->index();
                }
                if (! Schema::hasColumn('orders', 'authnet_transaction_id')) {
                    $table->string('authnet_transaction_id')->nullable()->index();
                }
            });
        }

        if (Schema::hasTable('stripe_events') && ! Schema::hasColumn('stripe_events', 'provider')) {
            Schema::table('stripe_events', function (Blueprint $table) {
                $table->string('provider', 32)->default('stripe')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                foreach (['payment_provider', 'authnet_transaction_id'] as $col) {
                    if (Schema::hasColumn('orders', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
        if (Schema::hasTable('stripe_events') && Schema::hasColumn('stripe_events', 'provider')) {
            Schema::table('stripe_events', function (Blueprint $table) {
                $table->dropColumn('provider');
            });
        }
    }
};
