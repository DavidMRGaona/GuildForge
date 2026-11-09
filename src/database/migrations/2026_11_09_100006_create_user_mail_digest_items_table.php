<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notices kept for each person's daily digest instead of being sent (core#333): one short line
 * each, already written. Rows are deleted once their digest is sent, after a week if it never
 * could be, when the account is anonymized and, by cascade, when it is deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_mail_digest_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('category', 100);
            $table->string('line', 200);
            $table->string('url', 2048)->nullable();
            // The one thing it is about, to drop it if the person mutes it before the digest
            $table->string('subject_type', 100)->nullable();
            $table->string('subject_id', 64)->nullable();
            $table->timestamp('created_at');

            $table->index(['user_id', 'created_at']);
            // Who is due a digest, and pruning what is too old
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_mail_digest_items');
    }
};
