<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The panel used to write module settings into each module's config/settings.php, a file
 * replaced with the module directory on every update. They now live in the settings table
 * under "module_settings:<name>": copy the values saved so far before an update loses them.
 *
 * Only files the panel wrote are copied (they start with the var_export signature
 * "return array ("; release files use short arrays): storing untouched release defaults
 * would freeze them and hide the defaults of later releases. A stored value is never
 * overwritten (venue-bookings already keeps its settings there).
 */
return new class extends Migration
{
    private const string PANEL_SIGNATURE = "<?php\n\nreturn array (";

    public function up(): void
    {
        $modules = DB::table('modules')->get(['name', 'path']);

        foreach ($modules as $module) {
            $path = is_string($module->path) && $module->path !== ''
                ? $module->path
                : rtrim((string) config('modules.path', base_path('modules')), '/').'/'.$module->name;
            $file = $path.'/config/settings.php';
            $key = 'module_settings:'.$module->name;

            if (! is_file($file) || DB::table('settings')->where('key', $key)->exists()) {
                continue;
            }

            if (! str_starts_with((string) file_get_contents($file), self::PANEL_SIGNATURE)) {
                continue;
            }

            try {
                $values = (static fn (string $settingsFile): mixed => require $settingsFile)($file);

                if (! is_array($values)) {
                    Log::warning("Settings of module {$module->name} are not an array; not copied");

                    continue;
                }

                // Inside the try: one file that cannot be encoded must not stop the deploy
                $json = json_encode($values, JSON_THROW_ON_ERROR);
            } catch (Throwable $e) {
                Log::warning("Settings of module {$module->name} could not be copied: {$e->getMessage()}");

                continue;
            }

            DB::table('settings')->insertOrIgnore([
                'id' => (string) Str::uuid(),
                'key' => $key,
                'value' => $json,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Cache::forget('site_settings');
    }

    public function down(): void
    {
        // Nothing to undo: once a module has been updated these rows are the only copy
    }
};
