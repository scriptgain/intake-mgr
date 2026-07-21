<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Shipping zones + their rates, and tax rules.
 *
 * A zone is a set of countries (optionally narrowed to states). Checkout picks
 * the first active zone matching the shipping address, then offers that zone's
 * rates. A rate is either flat, or banded by cart weight/price — `min_value`
 * and `max_value` are grams for weight bands and cents for price bands.
 *
 * Tax rates are basis points (725 = 7.25%) for the same reason discounts are:
 * no float columns anywhere in the money path.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shipping_zones')) {
            Schema::create('shipping_zones', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                // ISO-2 codes; ['*'] matches the rest of the world.
                $table->json('countries');
                // Null = the whole country; otherwise state/province codes.
                $table->json('states')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('position')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('shipping_rates')) {
            Schema::create('shipping_rates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('shipping_zone_id')->constrained()->cascadeOnDelete();
                $table->string('name');                      // "Standard (3-5 days)"
                $table->string('description')->nullable();
                $table->string('type')->default('flat');     // flat|weight|price|free
                $table->unsignedBigInteger('price_cents')->default(0);
                // Band bounds. Grams for weight, cents for price. Null = open end.
                $table->unsignedBigInteger('min_value')->nullable();
                $table->unsignedBigInteger('max_value')->nullable();
                // Free shipping above this subtotal, regardless of type.
                $table->unsignedBigInteger('free_above_cents')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('position')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tax_rules')) {
            Schema::create('tax_rules', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('country', 2)->default('US');
                $table->string('state')->nullable();         // null = whole country
                $table->string('postcode')->nullable();      // null = whole state
                $table->unsignedInteger('rate_bps')->default(0); // 725 = 7.25%
                // Which products this hits (products.tax_class).
                $table->string('tax_class')->default('standard');
                $table->boolean('applies_to_shipping')->default(false);
                // Higher priority wins when several rules match an address.
                $table->unsignedInteger('priority')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index(['country', 'state', 'is_active']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rules');
        Schema::dropIfExists('shipping_rates');
        Schema::dropIfExists('shipping_zones');
    }
};
