<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the author of an event proposal asked the modules for (core#581): one row per request
 * key, with the values asked for, the ones the reviewer approved, whether to apply it and when
 * it was applied. Deleting the event deletes its requests.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_proposal_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_id')->constrained('events')->cascadeOnDelete();
            // <module>.<name>
            $table->string('request_key', 100);
            $table->json('requested_values');
            // null until a reviewer saves them; then what apply() receives
            $table->json('approved_values')->nullable();
            $table->boolean('apply')->default(true);
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'request_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_proposal_requests');
    }
};
