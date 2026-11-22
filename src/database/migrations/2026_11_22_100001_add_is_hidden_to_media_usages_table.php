<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether the source keeps the image of a usage out of sight (core#830): the media library then shows a
 * placeholder instead of the file. Existing rows are shown, as before; the next sync writes the flag.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_usages', function (Blueprint $table): void {
            $table->boolean('is_hidden')->default(false)->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('media_usages', function (Blueprint $table): void {
            $table->dropColumn('is_hidden');
        });
    }
};
