<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Profile addresses no longer in use (core#740). Each one stays reserved for ever, so an old link never
 * points to another person; while its owner exists it redirects to their current address. Anonymizing
 * leaves only the hash: still reserved, naming nobody.
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
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('retired_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_profile_handles');
    }
};
