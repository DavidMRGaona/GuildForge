<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A site may now take the star off an official guide: `featured` null keeps what the guide file says,
 * true stars it and false unstars it. Until now false meant "as the file says", so those rows become null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_guide_overrides', function (Blueprint $table): void {
            $table->boolean('featured')->nullable()->default(null)->change();
        });

        DB::table('knowledge_guide_overrides')->where('featured', false)->update(['featured' => null]);
    }

    public function down(): void
    {
        DB::table('knowledge_guide_overrides')->whereNull('featured')->update(['featured' => false]);

        Schema::table('knowledge_guide_overrides', function (Blueprint $table): void {
            $table->boolean('featured')->default(false)->nullable(false)->change();
        });
    }
};
