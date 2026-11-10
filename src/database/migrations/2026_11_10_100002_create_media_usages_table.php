<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which records use each file, as reported by the media sources.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_usages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('file_id', 500)->index();
            $table->string('source_type', 100)->index();
            $table->string('module', 100)->nullable();
            $table->string('record_id', 100);
            $table->string('role', 100);
            $table->string('title', 255);
            $table->timestamps();

            $table->unique(['file_id', 'source_type', 'record_id', 'role'], 'media_usages_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_usages');
    }
};
