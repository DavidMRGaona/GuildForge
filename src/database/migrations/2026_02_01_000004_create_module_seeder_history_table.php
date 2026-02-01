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
        Schema::create('module_seeder_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('module_name');
            $table->string('seeder_class');
            $table->timestamp('executed_at');

            $table->unique(['module_name', 'seeder_class']);
            $table->index('module_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('module_seeder_history');
    }
};
