<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The account whose action a digest line tells about (core#537), so the line goes when that
 * account is anonymized. Lines kept before this have none and expire within a week.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_mail_digest_items', function (Blueprint $table): void {
            $table->string('actor_id', 64)->nullable();
            $table->index('actor_id');
        });
    }

    public function down(): void
    {
        Schema::table('user_mail_digest_items', function (Blueprint $table): void {
            $table->dropIndex(['actor_id']);
            $table->dropColumn('actor_id');
        });
    }
};
