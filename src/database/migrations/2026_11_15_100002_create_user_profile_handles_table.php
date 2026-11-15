<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every profile address ever given (core#740), written in the same transaction that gives it. The
 * primary key keeps each one reserved for ever, so an old link never points to another person; a retired
 * one redirects to its owner's current address while they exist. Anonymizing leaves only the hash: still
 * reserved, naming nobody.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profile_handles', function (Blueprint $table): void {
            // SHA-256 of the normalised handle
            $table->string('handle_hash', 64)->primary();
            $table->string('handle', 30)->nullable();
            $table->uuid('user_id')->nullable()->index();
            // Restrict, not null on delete: deleting an account around Eloquent would leave its addresses
            // readable. UserProfileServiceInterface::forget() reduces them to hashes first.
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            // Null while it is the owner's current address
            $table->timestamp('retired_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_profile_handles');
    }
};
