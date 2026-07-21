<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Theme Manager storage.
 *
 * A theme is a named bag of design tokens (colour ramp, typography, radius,
 * spacing rhythm) plus optional logo/favicon uploads. Exactly one theme is
 * active at a time; the active theme's tokens are inlined into the runtime
 * Tailwind build by App\Services\ThemeService.
 *
 * `tokens` is JSON rather than 20 columns because the token set is the thing
 * most likely to grow between releases, and a JSON column means adding a token
 * is a code change, not a migration on every merchant's install.
 *
 * `is_preset` marks the themes ShopMGR ships. Presets can be edited and
 * duplicated but not deleted, so a merchant can always get back to a known-good
 * starting point.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('themes')) {
            Schema::create('themes', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('description')->nullable();
                $table->boolean('is_active')->default(false);
                $table->boolean('is_preset')->default(false);
                $table->json('tokens')->nullable();
                $table->string('logo_path')->nullable();
                $table->string('favicon_path')->nullable();
                $table->timestamps();
                $table->index('is_active');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('themes');
    }
};
