<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Two shared polymorphic tables for the service-desk entities, so we don't
 * repeat a near-identical *_events / *_attachments pair for each of four models.
 *
 *   activities   the timeline for a ServiceRequest, Ticket, WorkOrder, Project.
 *                Mirrors order_events (type/message/meta/user), but polymorphic.
 *   attachments  uploaded files (photos of a leak, a signed estimate) hung off
 *                any subject. Only metadata + a private-disk path is stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activities')) {
            Schema::create('activities', function (Blueprint $table) {
                $table->id();
                $table->morphs('subject'); // subject_type + subject_id (indexed)
                // Null when the system (not a staff member) wrote the entry.
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('actor_name')->nullable(); // frozen for display
                $table->string('type'); // status|assigned|scheduled|reply|note|payment|created
                $table->string('message');
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['subject_type', 'subject_id', 'created_at'], 'activities_subject_time_idx');
            });
        }

        if (! Schema::hasTable('attachments')) {
            Schema::create('attachments', function (Blueprint $table) {
                $table->id();
                $table->morphs('attachable');
                $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('uploaded_by_customer_id')->nullable()->constrained('customers')->nullOnDelete();
                $table->string('disk')->default('local');
                $table->string('path');
                $table->string('filename');
                $table->string('mime')->nullable();
                $table->unsignedBigInteger('size')->default(0);
                $table->boolean('is_internal')->default(false);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('activities');
    }
};
