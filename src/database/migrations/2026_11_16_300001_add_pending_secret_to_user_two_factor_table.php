<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The secret of a new authenticator app, kept apart while the person changes app (core#769): the
 * secret in use keeps working until a code from the new one is confirmed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_two_factor', function (Blueprint $table): void {
            // The Base32 secret waiting to be confirmed, encrypted with the application key
            $table->text('pending_secret')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('user_two_factor', function (Blueprint $table): void {
            $table->dropColumn('pending_secret');
        });
    }
};
