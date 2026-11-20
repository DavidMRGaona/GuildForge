<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The browsers that receive push messages for each account (core#803). The endpoint can be long,
 * so its SHA-256 is the unique key; the browser's keys are stored encrypted (text, not varchar).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('web_push_subscriptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('endpoint');
            $table->char('endpoint_hash', 64)->unique();
            $table->text('p256dh');
            $table->text('auth');
            $table->string('browser', 20);
            $table->string('platform', 20);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_push_subscriptions');
    }
};
