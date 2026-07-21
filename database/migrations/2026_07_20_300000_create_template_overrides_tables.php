<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Template Manager storage.
 *
 * Merchant template edits are stored in the DATABASE, never written back into
 * resources/views. Two reasons, both of which would otherwise cost the merchant
 * their storefront:
 *
 *   1. ShopMGR self-updates from signed ScriptGain releases. A release rsyncs
 *      resources/views, so a file edit would be silently clobbered on update.
 *   2. Conversely, a merchant edit sitting in the release tree would make the
 *      updater's own file comparison dirty and block the update.
 *
 * A DB override layer keeps both sides clean: releases own the filesystem,
 * merchants own the override table, and "reset to shipped default" is a DELETE.
 *
 * Versions deliberately survive their parent override (nullOnDelete, plus a
 * denormalised `view` column). Resetting a template to the shipped default must
 * not incinerate the merchant's edit history, otherwise "reset" is a trap
 * rather than an escape hatch.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('template_overrides')) {
            Schema::create('template_overrides', function (Blueprint $table) {
                $table->id();
                // Dotted Blade view name, e.g. "shop.product". One override per view.
                $table->string('view')->unique();
                $table->longText('source');
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('template_override_versions')) {
            Schema::create('template_override_versions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('template_override_id')->nullable()
                    ->constrained('template_overrides')->nullOnDelete();
                $table->string('view')->index();
                // Null source == "reset to shipped default" was performed here.
                $table->longText('source')->nullable();
                // save | revert | reset | import
                $table->string('action')->default('save');
                $table->string('note')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['view', 'id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('template_override_versions');
        Schema::dropIfExists('template_overrides');
    }
};
