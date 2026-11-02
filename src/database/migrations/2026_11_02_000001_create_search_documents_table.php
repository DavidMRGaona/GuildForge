<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The documents of the site-wide search: one row per searchable record offered by an indexed source.
 * No foreign keys on purpose; (type, source_id) is the stable key of the record in its own module.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type', 100)->index();
            $table->string('source_id', 100);
            $table->string('module')->nullable();
            $table->string('title');
            $table->text('body');
            $table->text('keywords')->nullable();
            $table->string('meta')->nullable();
            $table->string('url', 2048);
            $table->string('image_public_id')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->boolean('finished')->default(false);
            $table->string('audience', 20)->index();
            $table->string('locale', 10);
            $table->timestamps();

            $table->unique(['type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_documents');
    }
};
