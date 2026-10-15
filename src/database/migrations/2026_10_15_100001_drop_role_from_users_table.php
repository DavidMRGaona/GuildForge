<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the legacy users.role column. Roles live in the user_role pivot and the code has
 * stopped reading and writing the column, so dropping it changes nothing. Values it still
 * held are discarded: they granted nothing, and down() recreates the column with the
 * default for every row.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'role')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('role');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'role')) {
            return;
        }

        // As created in 0001_01_01_000000_create_users_table; every row gets the default
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role')->default('member');
        });
    }
};
