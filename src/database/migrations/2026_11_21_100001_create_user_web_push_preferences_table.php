<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The categories each person chose to receive as browser push notifications (core#804). Only
 * those turned on have a row: without one, nothing is pushed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_web_push_preferences', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('category', 100);
            $table->timestamps();

            $table->unique(['user_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_web_push_preferences');
    }
};
