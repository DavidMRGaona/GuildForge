<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Until the storage_timezone setting existed every site stored Europe/Madrid wall times, but
 * without the row the zone is APP_STORAGE_TIMEZONE_DEFAULT: setting it to UTC on a site that was
 * never converted would misread every stored date. Record Madrid explicitly on those sites.
 *
 * "Already holds data" means at least one row in users: every site that has been used has its
 * administrator account, while a fresh install (and the test databases) is migrated before any
 * account exists, so it keeps following the variable. Written without Eloquent, like the other
 * data migrations; the key is StorageTimezoneResolver::SETTING_KEY.
 */
return new class extends Migration
{
    private const string KEY = 'storage_timezone';

    private const string MADRID = 'Europe/Madrid';

    public function up(): void
    {
        if (DB::table('settings')->where('key', self::KEY)->exists() || ! DB::table('users')->exists()) {
            return;
        }

        // Stamped in the zone the row declares, as the conversion command stamps it
        $now = Carbon::now(self::MADRID)->toDateTimeString();

        DB::table('settings')->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'key' => self::KEY,
            'value' => self::MADRID,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        // Deliberately a no-op: removing a storage marker is never safe. A site reverted this way
        // would fall back to APP_STORAGE_TIMEZONE_DEFAULT, and with a UTC default it would read its
        // Madrid data as UTC. An explicit Madrid row left behind is harmless.
    }
};
