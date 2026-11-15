<?php

declare(strict_types=1);

use App\Application\Services\SettingsServiceInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The "email verification required" setting was only shown to the frontend, so accounts that
 * never verified their email kept working. Enforcing it now must not lock them out: it only
 * applies to accounts created from this moment on. Record that moment once (UTC, ISO 8601) in
 * the settings table, in the format SettingsService writes; a later run never moves it.
 * Written without Eloquent, like the other data migrations.
 */
return new class extends Migration
{
    private const string KEY = 'email_verification_enforced_since';

    public function up(): void
    {
        if (DB::table('settings')->where('key', self::KEY)->exists()) {
            return;
        }

        DB::table('settings')->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'key' => self::KEY,
            'value' => Carbon::now('UTC')->toIso8601String(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // SettingsService caches every setting: a stale copy would read as "no cutoff" for an hour
        // and lock out every unverified account. The migrator runs this inside a transaction on
        // PostgreSQL; clearCache() invalidates only after the commit (at once outside one), so a
        // request in between cannot cache the settings without the uncommitted row again.
        app(SettingsServiceInterface::class)->clearCache();
    }

    public function down(): void
    {
        // Nothing to undo: removing the cutoff would demand verification from every account
    }
};
