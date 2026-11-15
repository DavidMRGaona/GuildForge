<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the owner last chose the address of their profile (core#743): they may choose again once every
 * 30 days. Moves that follow the public name leave it alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table): void {
            $table->timestamp('handle_changed_at')->nullable()->after('handle_chosen');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table): void {
            $table->dropColumn('handle_changed_at');
        });
    }
};
