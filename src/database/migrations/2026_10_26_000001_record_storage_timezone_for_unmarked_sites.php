<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The storage default is UTC from this release on. A site installed after the storage_timezone
 * setting existed was migrated before its first account, so 2026_10_25_000001 left it without a
 * marker, and it has been storing in APP_STORAGE_TIMEZONE_DEFAULT: Europe/Madrid when the variable
 * is not set (the previous default). Record that zone, so the new default never reinterprets it.
 *
 * Same "already holds data" test as 2026_10_25_000001 (a row in users). The variable is read as
 * set (app.storage_timezone_variable), since app.storage_timezone_default already falls back to
 * the new default. An unsupported value writes nothing: the application refuses to boot with it.
 */
return new class extends Migration
{
    private const string KEY = 'storage_timezone';

    private const string PREVIOUS_DEFAULT = 'Europe/Madrid';

    /** @var list<string> */
    private const array ALLOWED = ['Europe/Madrid', 'UTC'];

    public function up(): void
    {
        if (DB::table('settings')->where('key', self::KEY)->exists() || ! DB::table('users')->exists()) {
            return;
        }

        $variable = config('app.storage_timezone_variable');
        $zone = is_string($variable) && $variable !== '' ? $variable : self::PREVIOUS_DEFAULT;

        if (! in_array($zone, self::ALLOWED, true)) {
            return;
        }

        // Stamped in the zone the row declares, as the conversion command stamps it
        $now = Carbon::now($zone)->toDateTimeString();

        DB::table('settings')->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'key' => self::KEY,
            'value' => $zone,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        // Deliberately a no-op, as in 2026_10_25_000001: removing a storage marker is never safe
    }
};
