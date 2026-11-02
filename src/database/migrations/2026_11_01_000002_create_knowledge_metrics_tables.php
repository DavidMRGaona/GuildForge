<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anonymous knowledge base metrics: counters per day, never tied to a user.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_guide_feedback', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('guide_id');
            $table->date('day');
            $table->unsignedInteger('helpful_count')->default(0);
            $table->unsignedInteger('not_helpful_count')->default(0);

            $table->unique(['guide_id', 'day']);
            $table->index('day');
        });

        Schema::create('knowledge_search_misses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('normalized_query', 100);
            $table->string('audience');
            $table->date('day');
            $table->unsignedInteger('count')->default(0);

            $table->unique(['normalized_query', 'audience', 'day']);
            $table->index('day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_search_misses');
        Schema::dropIfExists('knowledge_guide_feedback');
    }
};
