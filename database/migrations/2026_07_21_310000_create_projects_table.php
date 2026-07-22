<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Projects group related tickets and work orders under one engagement (a pool
 * remodel, a seasonal maintenance contract). One lifecycle axis:
 *   status  planning | active | on_hold | completed | cancelled
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('projects')) {
            Schema::create('projects', function (Blueprint $table) {
                $table->id();
                $table->string('number')->unique(); // PRJ-1001
                $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('status')->default('planning');
                $table->date('starts_on')->nullable();
                $table->date('due_on')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'created_at']);
                $table->index('customer_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
