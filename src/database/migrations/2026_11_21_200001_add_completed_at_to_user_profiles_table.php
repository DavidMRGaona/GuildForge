<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a public profile became complete (core#777): public name, avatar and «Sobre mí». It is the
 * date the profile-completed activity source reports, so it must not move when the owner changes
 * something unrelated. Written only by ProfileCompletion; cleared when the profile stops being
 * complete. Schema only: profiles that were already complete are stamped by
 * `profiles:stamp-completed`, which a site runs once after deploying.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table): void {
            $table->timestamp('completed_at')->nullable()->after('layout');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table): void {
            $table->dropColumn('completed_at');
        });
    }
};
