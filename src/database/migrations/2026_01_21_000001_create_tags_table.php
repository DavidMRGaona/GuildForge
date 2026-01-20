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
        Schema::create('tags', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->uuid('parent_id')->nullable();
            $table->json('applies_to');
            $table->string('color', 7)->default('#6b7280');
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('parent_id');
            $table->index('sort_order');
        });

        // Add self-referential foreign key after table creation
        Schema::table('tags', function (Blueprint $table) {
            $table->foreign('parent_id')
                ->references('id')
                ->on('tags')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tags');
    }
};
