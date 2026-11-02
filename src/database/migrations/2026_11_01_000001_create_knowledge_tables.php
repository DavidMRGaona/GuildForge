<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The knowledge base catalog. Guides are synchronized from files; no foreign keys on purpose,
 * because guide and category ids are stable string keys that outlive renames (see aliases).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_categories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('category_id')->unique();
            $table->string('module')->nullable();
            $table->string('audience');
            $table->integer('sort_order')->default(0);
            $table->string('icon');
            $table->string('label_key');
            $table->timestamps();
        });

        Schema::create('knowledge_guides', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('guide_id');
            $table->string('locale', 10);
            $table->string('source');
            $table->string('module')->nullable()->index();
            $table->string('audience')->index();
            $table->string('category_id');
            $table->integer('sort_order')->default(0);
            $table->string('title');
            $table->text('summary');
            $table->json('keywords');
            $table->string('body_format');
            $table->longText('body');
            $table->longText('body_text');
            $table->json('conditions');
            $table->json('related');
            $table->boolean('featured')->default(false);
            $table->string('content_hash', 40);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('content_changed_at')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();

            $table->unique(['guide_id', 'locale']);
        });

        Schema::create('knowledge_guide_aliases', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('alias_id')->unique();
            $table->string('guide_id');
            $table->timestamps();
        });

        // Per-guide editorial overrides (used by the admin panel in a later phase)
        Schema::create('knowledge_guide_overrides', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('guide_id')->unique();
            $table->boolean('hidden')->default(false);
            $table->boolean('featured')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_guide_overrides');
        Schema::dropIfExists('knowledge_guide_aliases');
        Schema::dropIfExists('knowledge_guides');
        Schema::dropIfExists('knowledge_categories');
    }
};
