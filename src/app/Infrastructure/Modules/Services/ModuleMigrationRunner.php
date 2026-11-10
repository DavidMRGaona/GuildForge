<?php

declare(strict_types=1);

namespace App\Infrastructure\Modules\Services;

use App\Application\Modules\Services\ModuleMigrationAnalyzerInterface;
use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Exceptions\ModuleMigrationViolationException;
use App\Domain\Modules\Exceptions\ModuleNotFoundException;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;

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

        if ($migrationFiles === false || $migrationFiles === []) {
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
     * Undo every migration of a module, whenever it ran. `migrate:reset` goes through all the
     * migrations that ran and undoes only the ones in the module's path; `rollback --step` would
     * take the last N of the whole table, and migrations of the core or other modules run later
     * would leave some of the module's behind.
     *
     * @param  Module  $module  The module to undo all migrations for
     * @return int The number of migrations undone
     *
     * @throws ModuleNotFoundException If the module directory does not exist
     * @throws RuntimeException If the migrator reports a failure without throwing
     */
    public function rollbackAll(Module $module): int
    {
        $migrationsPath = $this->resolveModulePath($module).'/database/migrations';

        if (! is_dir($migrationsPath) || ! app()->bound('migrator')) {
            return 0;
        }

        $names = array_map(
            static fn (string $file): string => basename($file, '.php'),
            glob($migrationsPath.'/*.php') ?: [],
        );

        if ($names === []) {
            return 0;
        }

        $before = $this->ranCount($names);

        $exitCode = Artisan::call('migrate:reset', [
            // Absolute and real: a module outside base_path() resets too (tests)
            '--path' => $migrationsPath,
            '--realpath' => true,
            '--force' => true,
        ]);

        if ($exitCode !== 0) {
            throw new RuntimeException(trim(Artisan::output()) ?: "migrate:reset exited with code {$exitCode}");
        }

        $left = $this->ranCount($names);

        if ($left > 0) {
            throw new RuntimeException("{$left} migration(s) of the module could not be undone");
        }

        return $before;
    }

    /**
     * How many of the named migrations are recorded as run.
     *
     * @param  list<string>  $names
     */
    private function ranCount(array $names): int
    {
        return count(array_intersect($names, app('migrator')->getRepository()->getRan()));
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
        return (new ModulePathResolver($this->modulesPath))->resolve($module);
    }
}
