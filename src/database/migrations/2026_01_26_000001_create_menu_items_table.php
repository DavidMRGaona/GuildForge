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
        Schema::create('menu_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('location'); // 'header', 'footer'
            $table->uuid('parent_id')->nullable();
            $table->string('label');
            $table->string('url')->nullable();
            $table->string('route')->nullable();
            $table->json('route_params')->nullable();
            $table->string('icon')->nullable();
            $table->string('target')->default('_self'); // '_self', '_blank'
            $table->string('visibility')->default('public'); // 'public', 'authenticated', 'guests', 'permission'
            $table->json('permissions')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('module')->nullable(); // If contributed by a module
            $table->timestamps();

            $table->index(['location', 'is_active', 'sort_order']);
            $table->index('parent_id');
            $table->index('module');
        });

        // Add self-referencing foreign key after table creation (PostgreSQL requirement)
        Schema::table('menu_items', function (Blueprint $table) {
            $table->foreign('parent_id')
                ->references('id')
                ->on('menu_items')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
        });
        Schema::dropIfExists('menu_items');
    }
};
