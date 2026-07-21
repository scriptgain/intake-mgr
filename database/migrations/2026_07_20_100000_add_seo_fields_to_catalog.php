<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-entity SEO fields for products and collections.
 *
 * Both tables already carried seo_title / seo_description from the original
 * catalog migration. Those columns are kept and backfilled INTO the new
 * meta_title / meta_description so no merchant copy is lost, and the SEO
 * resolver reads meta_* first with seo_* as the next fallback. Nothing is
 * dropped here: dropping a populated column in a product update is not a
 * trade worth making for tidiness.
 */
return new class extends Migration
{
    /** @var array<int, string> */
    private array $tables = ['products', 'collections'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                if (! Schema::hasColumn($table, 'meta_title')) {
                    $t->string('meta_title', 255)->nullable();
                }
                if (! Schema::hasColumn($table, 'meta_description')) {
                    $t->text('meta_description')->nullable();
                }
                if (! Schema::hasColumn($table, 'og_image')) {
                    $t->string('og_image', 500)->nullable();
                }
                if (! Schema::hasColumn($table, 'canonical_url')) {
                    $t->string('canonical_url', 500)->nullable();
                }
                if (! Schema::hasColumn($table, 'noindex')) {
                    $t->boolean('noindex')->default(false);
                }
            });

            // Carry the legacy values forward so an existing store keeps the
            // titles it already wrote, without a merchant re-typing them.
            if (Schema::hasColumn($table, 'seo_title') && Schema::hasColumn($table, 'meta_title')) {
                DB::table($table)
                    ->whereNull('meta_title')
                    ->whereNotNull('seo_title')
                    ->update(['meta_title' => DB::raw('seo_title')]);
            }
            if (Schema::hasColumn($table, 'seo_description') && Schema::hasColumn($table, 'meta_description')) {
                DB::table($table)
                    ->whereNull('meta_description')
                    ->whereNotNull('seo_description')
                    ->update(['meta_description' => DB::raw('seo_description')]);
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                foreach (['meta_title', 'meta_description', 'og_image', 'canonical_url', 'noindex'] as $column) {
                    if (Schema::hasColumn($table, $column)) {
                        $t->dropColumn($column);
                    }
                }
            });
        }
    }
};
