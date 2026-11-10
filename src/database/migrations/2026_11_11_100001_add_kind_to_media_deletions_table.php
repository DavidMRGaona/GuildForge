<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a deletion was made by hand from the library or when a record let go of an unused file.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_deletions', function (Blueprint $table): void {
            $table->string('kind', 10)->default('manual');
        });
    }

    public function down(): void
    {
        Schema::table('media_deletions', function (Blueprint $table): void {
            $table->dropColumn('kind');
        });
    }
};
