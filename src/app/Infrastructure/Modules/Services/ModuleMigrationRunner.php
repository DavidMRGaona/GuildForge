<?php

declare(strict_types=1);

namespace App\Infrastructure\Modules\Services;

use App\Application\Modules\Services\ModuleMigrationAnalyzerInterface;
use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Exceptions\ModuleMigrationViolationException;
use App\Domain\Modules\Exceptions\ModuleNotFoundException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

final readonly class ModuleMigrationRunner
{
    public function __construct(
        private string $modulesPath,
        private ModuleMigrationAnalyzerInterface $analyzer,
        private ModuleSchemaGuard $schemaGuard,
    ) {}

    /**
     * Runs migrations for a module.
     *
     * @param  Module  $module  The module to run migrations for
     * @return int The number of migrations run
     *
     * @throws ModuleNotFoundException If the module directory does not exist
     * @throws ModuleMigrationViolationException If the module contains prohibited operations
     */
    public function run(Module $module): int
    {
        $migrationsPath = $this->resolveModulePath($module).'/database/migrations';

        if (! is_dir($migrationsPath)) {
            return 0;
        }

        $migrationFiles = glob($migrationsPath.'/*.php');

        if ($migrationFiles === false || empty($migrationFiles)) {
            return 0;
        }

        // Static analysis: check migrations for prohibited operations before execution
        $this->analyzer->analyzeMigrations($module->name()->value, $migrationsPath);

        // Run migrations from the module's migrations path if Laravel is fully booted
        // This check prevents errors in unit tests where the application isn't available
        $this->runMigrationsIfPossible($module->name()->value, $migrationsPath);

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
        $migrationsPath = $this->resolveModulePath($module).'/database/migrations';

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
        $migrationsPath = $this->resolveModulePath($module).'/database/migrations';

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
     * Run migrations if Laravel is fully booted. Errors reach the caller: the ZIP update
     * restores its backup on them, and nobody is told migrations ran when they did not.
     */
    private function runMigrationsIfPossible(string $moduleName, string $migrationsPath): void
    {
        // Without the migrator (plain unit tests) there is nothing to run them with
        if (! app()->bound('migrator')) {
            return;
        }

        // Runtime guard: protect core tables during migration execution
        $this->schemaGuard->protect($moduleName, function () use ($migrationsPath): void {
            Artisan::call('migrate', [
                // Absolute and real: a module outside base_path() migrates too (tests)
                '--path' => $migrationsPath,
                '--realpath' => true,
                '--force' => true,
            ]);
        });
    }

    /**
     * Rollback migrations if Laravel is fully booted. Errors reach the caller.
     */
    private function rollbackMigrationsIfPossible(string $migrationsPath, int $steps): int
    {
        if (! app()->bound('migrator')) {
            return 0;
        }

        Artisan::call('migrate:rollback', [
            // Absolute and real: a module outside base_path() rolls back too (tests)
            '--path' => $migrationsPath,
            '--realpath' => true,
            '--step' => $steps,
            '--force' => true,
        ]);

        return $steps;
    }

    /**
     * The stored path, or the modules directory when the module was moved there.
     *
     * @throws ModuleNotFoundException
     */
    private function resolveModulePath(Module $module): string
    {
        $modulePath = $module->path();

        if (is_dir($modulePath)) {
            return $modulePath;
        }

        $defaultPath = $this->modulesPath.'/'.$module->name()->value;

        if (! is_dir($defaultPath)) {
            throw ModuleNotFoundException::withName($module->name()->value);
        }

        Log::warning("Stored path of module {$module->name()->value} does not exist, using the modules directory", [
            'stored_path' => $modulePath,
            'path' => $defaultPath,
        ]);

        return $defaultPath;
    }
}
