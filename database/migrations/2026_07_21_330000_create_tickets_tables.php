<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Tickets are the service-desk conversation. One lifecycle axis plus a priority:
 *   status    open | pending | in_progress | resolved | closed
 *   priority  low | normal | high | urgent
 * Replies are the thread: a reply is either customer-visible or an internal
 * staff note (is_internal). Attachments and the timeline live in the shared
 * polymorphic tables (see create_activities_and_attachments).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tickets')) {
            Schema::create('tickets', function (Blueprint $table) {
                $table->id();
                $table->string('number')->unique(); // TKT-1001
                $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('service_request_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();

                $table->string('subject');
                $table->text('description')->nullable();
                $table->string('status')->default('open');
                $table->string('priority')->default('normal');

                $table->timestamp('last_reply_at')->nullable();
                $table->string('last_reply_by')->nullable(); // customer|staff
                $table->timestamp('resolved_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'created_at']);
                $table->index('priority');
                $table->index('customer_id');
            });
        }

        if (! Schema::hasTable('ticket_replies')) {
            Schema::create('ticket_replies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
                // Exactly one of these is set: a staff user or a customer.
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
                $table->string('author_type')->default('staff'); // staff|customer|system
                $table->string('author_name')->nullable(); // frozen for display
                $table->text('body');
                // Internal notes are never shown to the customer portal.
                $table->boolean('is_internal')->default(false);
                $table->timestamps();

                $table->index(['ticket_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_replies');
        Schema::dropIfExists('tickets');
    }
};
