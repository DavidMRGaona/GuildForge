<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Media source types whose last refresh of a record failed (core#844): the index may be stale for them until
 * the next sync that reads them completely. One row per type, so failures written at once never lose each other.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_source_refresh_failures', function (Blueprint $table): void {
            $table->string('source_type', 100)->primary();
            // New on every failure: a sync clears only the failure it saw before reading the source
            $table->uuid('token');
            $table->timestamp('failed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_source_refresh_failures');
    }
};
