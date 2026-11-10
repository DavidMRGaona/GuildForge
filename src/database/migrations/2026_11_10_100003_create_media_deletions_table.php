<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permanent history of files deleted from the library; rows are never removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_deletions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('file_id', 500);
            $table->string('resource_type', 10);
            $table->string('folder', 500);
            $table->string('source_type', 100)->nullable();
            $table->unsignedBigInteger('bytes')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            // "deleted" or "missing" (already gone from Cloudinary)
            $table->string('outcome', 10);
            $table->foreignUuid('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_deletions');
    }
};
