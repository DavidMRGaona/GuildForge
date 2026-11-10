<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each person's site switch per mail category (core#491), apart from the mail choices so the
 * opt-out statistics and the unsubscribe links of the mail stay as they are. No row means the
 * notices are shown.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_site_notification_preferences', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('category', 100);
            $table->boolean('enabled');
            $table->timestamps();

            $table->unique(['user_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_site_notification_preferences');
    }
};
