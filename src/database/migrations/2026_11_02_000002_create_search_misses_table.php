<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anonymous counters of site searches without results: per query and day, never tied to a user.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_misses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('normalized_query', 100);
            $table->date('day');
            $table->unsignedInteger('count')->default(0);

            $table->unique(['normalized_query', 'day']);
            $table->index('day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_misses');
    }
};
