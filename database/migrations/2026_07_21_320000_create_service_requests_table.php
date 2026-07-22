<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Service requests are the front door: what a resident/customer submits through
 * the public "Request Service" form (or what staff log by phone). They land in
 * a triage inbox and are converted into a ticket and/or a work order.
 *   status  new | triaged | converted | closed
 * The contact fields are frozen on the request even when no customer account
 * exists yet; converting later can attach or create a Customer.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('service_requests')) {
            Schema::create('service_requests', function (Blueprint $table) {
                $table->id();
                $table->string('number')->unique(); // REQ-1001
                $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
                // The service the requester picked, if any. Nulled on delete so
                // retiring a service never erases the request that named it.
                $table->foreignId('service_id')->nullable()->constrained('products')->nullOnDelete();

                $table->string('name');
                $table->string('email');
                $table->string('phone')->nullable();
                $table->json('address')->nullable();

                $table->string('subject');
                $table->text('description')->nullable();

                $table->string('status')->default('new');
                $table->string('priority')->default('normal'); // low|normal|high|urgent
                $table->string('source')->default('web'); // web|phone|email|staff

                $table->foreignId('ticket_id')->nullable(); // set when converted
                $table->foreignId('work_order_id')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'created_at']);
                $table->index('email');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('service_requests');
    }
};
