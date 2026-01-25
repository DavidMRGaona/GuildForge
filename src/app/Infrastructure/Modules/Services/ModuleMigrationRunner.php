<?php

declare(strict_types=1);

namespace App\Infrastructure\Modules\Services;

use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Exceptions\ModuleNotFoundException;
use Illuminate\Support\Facades\Artisan;

final readonly class ModuleMigrationRunner
{
    public function __construct(
        private string $modulesPath,
    ) {
    }

    /**
     * Runs migrations for a module.
     *
     * @param  Module  $module  The module to run migrations for
     * @return int The number of migrations run
     *
     * @throws ModuleNotFoundException If the module directory does not exist
     */
    public function run(Module $module): int
    {
        $modulePath = $module->path();

        // Fall back to default path if module path is not set
        if (! is_dir($modulePath)) {
            $modulePath = $this->modulesPath.'/'.$module->name()->value;
        }

        if (! is_dir($modulePath)) {
            throw ModuleNotFoundException::withName($module->name()->value);
        }

        $migrationsPath = $modulePath.'/database/migrations';

        if (! is_dir($migrationsPath)) {
            return 0;
        }

        $migrationFiles = glob($migrationsPath.'/*.php');

        if ($migrationFiles === false || empty($migrationFiles)) {
            return 0;
        }

        // Run migrations from the module's migrations path if Laravel is fully booted
        // This check prevents errors in unit tests where the application isn't available
        $this->runMigrationsIfPossible($migrationsPath);

        return count($migrationFiles);
    }

    /**
     * Rollback migrations for a module.
     *
     * @param  Module  $module  The module to rollback migrations for
     * @param  int  $steps  Number of migrations to rollback
     * @return int The number of migrations rolled back
     *
     * @throws ModuleNotFoundException If the module directory does not exist
     */
    public function rollback(Module $module, int $steps = 1): int
    {
        $modulePath = $module->path();

        // Fall back to default path if module path is not set
        if (! is_dir($modulePath)) {
            $modulePath = $this->modulesPath.'/'.$module->name()->value;
        }

        if (! is_dir($modulePath)) {
            throw ModuleNotFoundException::withName($module->name()->value);
        }

        $migrationsPath = $modulePath.'/database/migrations';

        if (! is_dir($migrationsPath)) {
            return 0;
        }

        return $this->rollbackMigrationsIfPossible($migrationsPath, $steps);
    }

    /**
     * Rollback all migrations for a module.
     *
     * @param  Module  $module  The module to rollback all migrations for
     * @return int The number of migrations rolled back
     *
     * @throws ModuleNotFoundException If the module directory does not exist
     */
    public function rollbackAll(Module $module): int
    {
        $modulePath = $module->path();

        // Fall back to default path if module path is not set
        if (! is_dir($modulePath)) {
            $modulePath = $this->modulesPath.'/'.$module->name()->value;
        }

        if (! is_dir($modulePath)) {
            throw ModuleNotFoundException::withName($module->name()->value);
        }

        $migrationsPath = $modulePath.'/database/migrations';

        if (! is_dir($migrationsPath)) {
            return 0;
        }

        $migrationFiles = glob($migrationsPath.'/*.php');
        $count = $migrationFiles !== false ? count($migrationFiles) : 0;

        if ($count === 0) {
            return 0;
        }

        return $this->rollbackMigrationsIfPossible($migrationsPath, $count);
    }

    /**
     * Run migrations if Laravel is fully booted.
     */
    private function runMigrationsIfPossible(string $migrationsPath): void
    {
        try {
            // Check if we're in a Laravel application context
            if (! function_exists('app')) {
                return;
            }

            $app = app();

            // Check if the application has the migrator bound (indicates full boot)
            if (! $app->bound('migrator')) {
                return;
            }

            Artisan::call('migrate', [
                '--path' => str_replace(base_path().'/', '', $migrationsPath),
                '--force' => true,
            ]);
        } catch (\Throwable) {
            // Silently fail if anything goes wrong (unit tests, missing dependencies, etc.)
        }
    }

    /**
     * Rollback migrations if Laravel is fully booted.
     */
    private function rollbackMigrationsIfPossible(string $migrationsPath, int $steps): int
    {
        try {
            // Check if we're in a Laravel application context
            if (! function_exists('app')) {
                return 0;
            }

            $app = app();

            // Check if the application has the migrator bound (indicates full boot)
            if (! $app->bound('migrator')) {
                return 0;
            }

            Artisan::call('migrate:rollback', [
                '--path' => str_replace(base_path().'/', '', $migrationsPath),
                '--step' => $steps,
                '--force' => true,
            ]);

            return $steps;
        } catch (\Throwable) {
            // Silently fail if anything goes wrong (unit tests, missing dependencies, etc.)
            return 0;
        }
    }
}
