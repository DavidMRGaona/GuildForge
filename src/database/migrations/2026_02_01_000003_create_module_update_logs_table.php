<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('module_update_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('update_history_id');
            $table->string('step');
            $table->string('status');
            $table->text('message')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('created_at');

            $table->foreign('update_history_id')
                ->references('id')
                ->on('module_update_history')
                ->onDelete('cascade');

            $table->index(['update_history_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('module_update_logs');
    }
};
