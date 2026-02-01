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
        Schema::create('modules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('display_name')->nullable();
            $table->string('version');
            $table->text('description')->nullable();
            $table->string('author')->nullable();
            $table->string('status')->default('disabled');
            $table->string('path')->nullable();
            $table->string('namespace')->nullable();
            $table->string('provider')->nullable();
            $table->json('requires')->nullable();
            $table->json('dependencies')->nullable();
            $table->string('source_owner')->nullable();
            $table->string('source_repo')->nullable();
            $table->string('latest_available_version')->nullable();
            $table->timestamp('last_update_check_at')->nullable();
            $table->timestamp('discovered_at')->nullable();
            $table->timestamp('enabled_at')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
