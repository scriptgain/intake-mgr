<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Staff roles for the merchant admin. Guarded with hasColumn so a re-install
 * over an existing database is a no-op (fleet convention).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || Schema::hasColumn('users', 'role')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('staff')->after('email'); // admin|staff
        });

        // Whoever already exists predates roles and is the owner.
        DB::table('users')->update(['role' => 'admin']);
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role');
            });
        }
    }
};
