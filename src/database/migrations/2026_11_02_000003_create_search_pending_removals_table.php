<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Search documents removed from the table whose removal from the search engine failed or was skipped.
 * The id is the removed search_documents row id; the next sync or refresh sends it to the engine again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_pending_removals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            // Written by the app in the storage zone (no database default, which would fill it in another zone)
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_pending_removals');
    }
};
