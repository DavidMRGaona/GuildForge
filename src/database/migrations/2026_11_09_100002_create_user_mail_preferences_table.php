<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each person's choice per optional mail category. No row means the category is enabled, so
 * nobody stops receiving anything when this is deployed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_mail_preferences', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('category', 100);
            $table->boolean('enabled');
            $table->timestamps();

            $table->unique(['user_id', 'category']);
            $table->index(['category', 'enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_mail_preferences');
    }
};
