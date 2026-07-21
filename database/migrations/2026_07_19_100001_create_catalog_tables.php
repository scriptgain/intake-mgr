<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The catalog: collections, products, their variants, and their images.
 *
 * Money is stored as integer minor units (cents) everywhere — never a float —
 * so totals never drift. Display formatting is a view concern (Money support
 * class), not a storage concern.
 *
 * A product ALWAYS has at least one variant, even when the merchant never
 * defines options: the "default" variant carries the price, SKU, and stock.
 * That keeps every downstream table (cart items, order items, inventory) able
 * to reference exactly one thing instead of branching on has-options.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('collections')) {
            Schema::create('collections', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->string('image_path')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('position')->default(0);
                $table->string('seo_title')->nullable();
                $table->string('seo_description', 500)->nullable();
                $table->timestamps();
                $table->index(['is_active', 'position']);
            });
        }

        if (! Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('excerpt', 500)->nullable();
                $table->longText('description')->nullable();
                // draft = invisible on the storefront; archived = kept for order
                // history but delisted.
                $table->string('status')->default('draft'); // draft|active|archived
                $table->string('vendor')->nullable();
                $table->string('product_type')->nullable();
                // Which tax_rules rows apply (see tax_rules.tax_class).
                $table->string('tax_class')->default('standard');
                $table->boolean('requires_shipping')->default(true);
                $table->boolean('is_featured')->default(false);
                $table->string('seo_title')->nullable();
                $table->string('seo_description', 500)->nullable();
                $table->unsignedInteger('position')->default(0);
                $table->timestamps();
                $table->softDeletes();
                $table->index(['status', 'position']);
                $table->index('is_featured');
            });
        }

        if (! Schema::hasTable('collection_product')) {
            Schema::create('collection_product', function (Blueprint $table) {
                $table->id();
                $table->foreignId('collection_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->unsignedInteger('position')->default(0);
                $table->unique(['collection_id', 'product_id']);
            });
        }

        if (! Schema::hasTable('product_variants')) {
            Schema::create('product_variants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                // Human label, e.g. "Medium / Charcoal". Derived from the option
                // values but denormalised so order history stays readable after
                // the variant is renamed or deleted.
                $table->string('name')->default('Default');
                $table->string('sku')->nullable()->unique();
                $table->string('barcode')->nullable();

                // Up to three option axes, Shopify-style. Names repeat per
                // variant row so a variant is self-describing.
                $table->string('option1_name')->nullable();
                $table->string('option1_value')->nullable();
                $table->string('option2_name')->nullable();
                $table->string('option2_value')->nullable();
                $table->string('option3_name')->nullable();
                $table->string('option3_value')->nullable();

                $table->unsignedBigInteger('price_cents')->default(0);
                // Strike-through "was" price. Null = not on sale.
                $table->unsignedBigInteger('compare_at_price_cents')->nullable();
                // Merchant cost, for the margin column. Never shown publicly.
                $table->unsignedBigInteger('cost_cents')->nullable();

                $table->boolean('track_inventory')->default(true);
                $table->integer('inventory_qty')->default(0);
                $table->unsignedInteger('weight_grams')->default(0);
                $table->boolean('is_default')->default(false);
                $table->unsignedInteger('position')->default(0);
                $table->timestamps();
                $table->index(['product_id', 'position']);
            });
        }

        if (! Schema::hasTable('product_images')) {
            Schema::create('product_images', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                // Optional: pin an image to one variant so the gallery swaps
                // when the shopper picks that option.
                $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
                $table->string('path');
                $table->string('alt')->nullable();
                $table->unsignedInteger('position')->default(0);
                $table->timestamps();
                $table->index(['product_id', 'position']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('collection_product');
        Schema::dropIfExists('products');
        Schema::dropIfExists('collections');
    }
};
