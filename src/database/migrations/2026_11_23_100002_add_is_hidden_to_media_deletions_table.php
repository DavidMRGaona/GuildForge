<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether the library concealed the file when it was deleted (core#841): «Borrados» then does not name it by
 * its id, as the library did not. Existing rows keep their name, as before: nobody can tell any more.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_deletions', function (Blueprint $table): void {
            $table->boolean('is_hidden')->default(false)->after('kind');
        });
    }

    public function down(): void
    {
        Schema::table('media_deletions', function (Blueprint $table): void {
            $table->dropColumn('is_hidden');
        });
    }
};
