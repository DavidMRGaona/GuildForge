<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a person receives an enabled category as a daily digest (core#333). `enabled = false`
 * still means "not at all", so the opt-out statistics and the unsubscribe links are unchanged;
 * every existing row keeps receiving its mail at once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_mail_preferences', function (Blueprint $table): void {
            $table->boolean('digest')->default(false)->after('enabled');
        });
    }

    public function down(): void
    {
        Schema::table('user_mail_preferences', function (Blueprint $table): void {
            $table->dropColumn('digest');
        });
    }
};
