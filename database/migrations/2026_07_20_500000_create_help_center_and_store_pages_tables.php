<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The Help Center and the store's policy pages.
 *
 * Two shapes of merchant-authored content:
 *   - help_categories -> help_articles: a searchable FAQ the shopper browses.
 *   - store_pages: the flat legal/policy pages (shipping, refunds, terms,
 *     privacy) reached straight from the footer, no category in between.
 *
 * Bodies are stored as Markdown and rendered server-side (Str::markdown), so
 * there is no client WYSIWYG dependency and nothing raw is trusted from the
 * shopper side (only the merchant ever writes these).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('help_categories')) {
            Schema::create('help_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('description', 500)->nullable();
                // Icon name from the shared x-icon set (e.g. 'truck', 'credit-card').
                $table->string('icon')->default('book');
                $table->boolean('is_published')->default(true);
                $table->unsignedInteger('position')->default(0);
                $table->timestamps();
                $table->index(['is_published', 'position']);
            });
        }

        if (! Schema::hasTable('help_articles')) {
            Schema::create('help_articles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('help_category_id')->constrained('help_categories')->cascadeOnDelete();
                $table->string('title');
                $table->string('slug');
                $table->string('excerpt', 500)->nullable();
                $table->longText('body')->nullable();
                $table->boolean('is_published')->default(true);
                $table->unsignedInteger('position')->default(0);
                $table->unsignedInteger('views')->default(0);
                $table->timestamps();
                // Slugs are globally unique so an article resolves without its
                // category, but scoped route binding still checks the parent.
                $table->unique('slug');
                $table->index(['help_category_id', 'is_published', 'position']);
            });
        }

        if (! Schema::hasTable('store_pages')) {
            Schema::create('store_pages', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('slug')->unique();
                $table->longText('body')->nullable();
                $table->boolean('is_published')->default(true);
                $table->unsignedInteger('position')->default(0);
                $table->timestamps();
                $table->index(['is_published', 'position']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('help_articles');
        Schema::dropIfExists('help_categories');
        Schema::dropIfExists('store_pages');
    }
};
