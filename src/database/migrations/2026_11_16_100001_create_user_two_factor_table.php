<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two-factor authentication of each account that set it up (core#768), apart from `users` so the
 * secret never travels with the account model. Written only through TwoFactorServiceInterface.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_two_factor', function (Blueprint $table): void {
            $table->uuid('user_id')->primary();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            // The TOTP secret in Base32, encrypted with the application key
            $table->text('secret');
            // Null while it is being set up
            $table->timestamp('confirmed_at')->nullable();
            // Encrypted JSON list of SHA-256 hashes of the unused recovery codes, never the codes
            $table->text('recovery_codes')->nullable();
            // The last TOTP step accepted: only later ones are, so no code is used twice
            $table->bigInteger('last_used_timestep')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_two_factor');
    }
};
