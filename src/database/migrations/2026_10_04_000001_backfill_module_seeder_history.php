<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Module seeders now run once per installation. Installed modules were seeded before
 * that was tracked, so record their current seeders as executed: otherwise the next
 * update would run them again and overwrite what admins edited since.
 */
return new class extends Migration
{
    public function up(): void
    {
        $modules = DB::table('modules')->whereNotNull('installed_at')->get(['name', 'path', 'namespace']);

        foreach ($modules as $module) {
            $path = is_string($module->path) && $module->path !== ''
                ? $module->path
                : rtrim((string) config('modules.path', base_path('modules')), '/').'/'.$module->name;

            $seeders = glob($path.'/database/seeders/*Seeder.php') ?: [];

            foreach ($seeders as $seeder) {
                DB::table('module_seeder_history')->insertOrIgnore([
                    'id' => (string) Str::uuid(),
                    'module_name' => $module->name,
                    'seeder_class' => $module->namespace.'\\Database\\Seeders\\'.pathinfo($seeder, PATHINFO_FILENAME),
                    'executed_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Nothing to undo safely: backfilled rows cannot be told apart from real executions
    }
};
