<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Digest lines are deleted by the thing they are about when it is resolved or deleted (core#681):
 * an index on the subject keeps that from reading the whole table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_mail_digest_items', function (Blueprint $table): void {
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::table('user_mail_digest_items', function (Blueprint $table): void {
            $table->dropIndex(['subject_type', 'subject_id']);
        });
    }
};
