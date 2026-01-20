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
        Schema::create('gallery_tag', function (Blueprint $table) {
            $table->uuid('gallery_id');
            $table->uuid('tag_id');
            $table->timestamps();

            $table->primary(['gallery_id', 'tag_id']);

            $table->foreign('gallery_id')
                ->references('id')
                ->on('galleries')
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
        Schema::dropIfExists('gallery_tag');
    }
};
