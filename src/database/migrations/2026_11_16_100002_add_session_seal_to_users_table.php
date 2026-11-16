<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The session seal of each account (core#768): random, new whenever two-factor authentication is
 * turned on, off or reset, and copied into each session at sign-in. A session without the
 * account's current seal is signed out. Null until the first change, so no session is closed by
 * deploying this.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('session_seal', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('session_seal');
        });
    }
};
