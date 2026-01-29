<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slug_redirects', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('old_slug')->index();
            $table->string('new_slug')->index();
            $table->string('entity_type');
            $table->uuid('entity_id')->index();
            $table->timestamps();

            $table->unique(['old_slug', 'entity_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slug_redirects');
    }
};
