<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When a public profile became complete (core#777): public name, avatar and «Sobre mí». It is the
 * date the profile-completed activity source reports, so it must not move when the owner changes
 * something unrelated. Written only by ProfileCompletion; cleared when the profile stops being
 * complete. Profiles already complete take their last change as the best date there is.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table): void {
            $table->timestamp('completed_at')->nullable()->after('layout');
        });

        DB::table('user_profiles')
            ->whereIn('user_id', DB::table('users')
                ->select('id')
                ->whereNotNull('display_name')
                ->whereRaw("trim(display_name) <> ''")
                ->whereNotNull('avatar_public_id')
                ->whereRaw("trim(avatar_public_id) <> ''"))
            ->whereNotNull('bio')
            ->whereRaw("trim(bio) <> ''")
            ->update(['completed_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table): void {
            $table->dropColumn('completed_at');
        });
    }
};
