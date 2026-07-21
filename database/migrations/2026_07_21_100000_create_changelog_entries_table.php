<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Public, merchant-managed release notes.
 *
 * A flat list of dated entries (no categories): each is one shipped release
 * the merchant wants shoppers to see. Bodies are Markdown, rendered
 * server-side (Str::markdown) exactly like the Help Center, so there is no
 * client editor dependency and nothing untrusted is ever rendered (only the
 * merchant writes these). Newest first is the natural read order, so the
 * public timeline sorts by released_on desc with position as a tie-breaker.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('changelog_entries')) {
            Schema::create('changelog_entries', function (Blueprint $table) {
                $table->id();
                $table->string('version');
                $table->date('released_on');
                $table->string('title');
                $table->string('summary', 500)->nullable();
                $table->longText('body')->nullable();
                $table->boolean('is_published')->default(true);
                $table->unsignedInteger('position')->default(0);
                $table->timestamps();
                $table->index(['is_published', 'released_on']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('changelog_entries');
    }
};
