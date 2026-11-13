<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Core content already announced as news in the bell (core#613): one row per event, article or
 * gallery, so taking it down and publishing it again never announces it twice. No foreign keys:
 * the three kinds live in their own tables, and deleting the content deletes its row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_news_announcements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            // event, article or gallery (SiteNewsKind)
            $table->string('kind', 32);
            $table->uuid('subject_id');
            $table->timestamp('announced_at');

            $table->unique(['kind', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_news_announcements');
    }
};
