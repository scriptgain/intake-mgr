<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A staff member's connection to an external calendar. One row per user per
 * provider (google | microsoft | apple | nylas). OAuth tokens and the CalDAV
 * password are stored ENCRYPTED (the model casts them), so the columns are
 * text to hold the ciphertext. Apple uses CalDAV (Apple ID + app-specific
 * password), Nylas uses a grant id; the rest use OAuth access/refresh tokens.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('calendar_connections')) {
            Schema::create('calendar_connections', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('provider'); // google|microsoft|apple|nylas
                $table->string('account_email')->nullable();

                // OAuth (google/microsoft/nylas). Encrypted at the model.
                $table->text('access_token')->nullable();
                $table->text('refresh_token')->nullable();
                $table->timestamp('token_expires_at')->nullable();
                $table->text('scopes')->nullable();

                // Which remote calendar we write to, and incremental-sync cursors.
                $table->string('remote_calendar_id')->nullable();
                $table->text('sync_token')->nullable();    // Google syncToken
                $table->text('delta_link')->nullable();    // Graph deltaLink

                // Apple CalDAV.
                $table->string('caldav_url')->nullable();
                $table->string('caldav_username')->nullable();
                $table->text('caldav_password')->nullable(); // encrypted

                // Nylas.
                $table->string('nylas_grant_id')->nullable();

                $table->string('status')->default('connected'); // connected|error|revoked
                $table->timestamp('last_synced_at')->nullable();
                $table->string('last_error')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'provider']);
                $table->index('provider');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_connections');
    }
};
