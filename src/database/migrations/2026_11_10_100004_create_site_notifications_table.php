<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The notification centre of each account (core#491): one row per notice, or per group of unread
 * notices with the same key. Read rows go after 90 days and unread ones after 180
 * (`site-notifications:prune`), all of them when the account is anonymized and, by cascade, when
 * it is deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            // The mail category it belongs to, as the person turns it on and off
            $table->string('category', 100);
            $table->string('title', 160);
            $table->string('body', 300)->nullable();
            $table->string('url', 2048);
            $table->string('icon', 64)->nullable();
            // Unread rows with the same key take the next notice instead of a new row
            $table->string('group_key', 191)->nullable();
            $table->unsignedInteger('count')->default(1);
            // Distinct accounts behind the group, to say «Ana y 3 más»
            $table->json('actor_ids')->nullable();
            // The one thing it is about, to forget it when the thing is deleted
            $table->string('subject_type', 100)->nullable();
            $table->string('subject_id', 64)->nullable();
            // Instant of its latest notice, and the same in microseconds to sort the list
            // without ties inside one second
            $table->timestamp('notified_at');
            $table->unsignedBigInteger('sort_key');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // The bell: unread count and the newest rows of one account
            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'sort_key']);
            // The unread row of a group
            $table->index(['user_id', 'group_key', 'read_at']);
            $table->index(['subject_type', 'subject_id']);
            // Pruning
            $table->index('notified_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_notifications');
    }
};
