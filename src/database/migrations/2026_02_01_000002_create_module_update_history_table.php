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
        Schema::create('module_update_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('module_name');
            $table->string('from_version');
            $table->string('to_version');
            $table->string('status');
            $table->text('error_message')->nullable();
            $table->string('backup_path')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['module_name', 'started_at']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('module_update_history');
    }
};
