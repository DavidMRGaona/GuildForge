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
        Schema::create('article_tag', function (Blueprint $table) {
            $table->uuid('article_id');
            $table->uuid('tag_id');
            $table->timestamps();

            $table->primary(['article_id', 'tag_id']);

            $table->foreign('article_id')
                ->references('id')
                ->on('articles')
                ->cascadeOnDelete();

            $table->foreign('tag_id')
                ->references('id')
                ->on('tags')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('article_tag');
    }
};
