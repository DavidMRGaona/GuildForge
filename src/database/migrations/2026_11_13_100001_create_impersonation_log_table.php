<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The impersonation log: every time an administrator impersonated another account. No
 * foreign keys on purpose: the entries outlive both accounts. An entry without ended_at is
 * one whose session expired before it was ended.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impersonation_log', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('impersonator_id')->index();
            $table->uuid('target_id')->index();
            // read_only or simulate
            $table->string('mode', 32);
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            // stopped, logout or revoked; null while open
            $table->string('ended_reason', 32)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impersonation_log');
    }
};
