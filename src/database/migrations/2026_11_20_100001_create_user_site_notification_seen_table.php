<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When each person last looked at their notices (core#800): the bell counts the unread ones that
 * arrived after it. The mark is on the same clock as `site_notifications.sort_key` (microseconds),
 * so a notice delivered within the same second still tells apart. No row means everything unread
 * is new.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_site_notification_seen', function (Blueprint $table): void {
            $table->foreignUuid('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('seen_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_site_notification_seen');
    }
};
