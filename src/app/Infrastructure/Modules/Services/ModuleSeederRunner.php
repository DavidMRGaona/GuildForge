<?php

declare(strict_types=1);

namespace App\Infrastructure\Modules\Services;

use App\Application\Modules\Services\ModuleMigrationAnalyzerInterface;
use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Exceptions\ModuleNotFoundException;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Infrastructure\Updates\Persistence\Eloquent\Models\ModuleSeederHistoryModel;
use App\Modules\ModuleLoader;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

final readonly class ModuleSeederRunner
{
    public function __construct(
        private string $modulesPath,
        private ModuleMigrationAnalyzerInterface $analyzer,
        private ModuleLoader $loader,
    ) {}

    /**
     * Runs seeders for a module.
     *
     * @param  Module  $module  The module to run seeders for
     * @param  bool  $enabling  Whether this process is enabling the module right now
     * @return int The number of seeders run
     *
     * @throws ModuleNotFoundException If the module directory does not exist
     */
    public function run(Module $module, bool $enabling = false): int
    {
        return count($this->seed($module, $enabling));
    }

    /**
     * Run the module's seeders that have not run yet and return the classes executed.
     *
     * Each seeder runs once per installation: running it again on every update would
     * overwrite what admins edited or bring back what they deleted. A new version that
     * needs more default data ships a new seeder (or a data migration).
     *
     * Seeders are module code: they run with the module's provider booted, so they can resolve
     * what it binds. A provider not loaded yet is booted only for a module this process is
     * enabling ($enabling: its seeders run before it is enabled) or one that is enabled and
     * compatible. A disabled module is never booted, since a long-running process (a queue
     * worker) would keep its listeners, routes and slots: its pending seeders are left for
     * when it is enabled, which runs them.
     *
     * @return array<string>
     */
    public function seed(Module $module, bool $enabling = false): array
    {
        $modulePath = (new ModulePathResolver($this->modulesPath))->resolve($module);

        $seedersPath = $modulePath.'/database/seeders';

        if (! is_dir($seedersPath)) {
            return [];
        }

        $seederFiles = glob($seedersPath.'/*Seeder.php');

        if ($seederFiles === false || $seederFiles === []) {
            return [];
        }

        // Static analysis: check seeders for prohibited operations before execution
        $this->analyzer->analyzeSeeders($module->name()->value, $seedersPath);

        // Run seeders if Laravel is fully booted
        return $this->runSeedersIfPossible($module, $seederFiles, $enabling);
    }

    /**
     * Run seeders if Laravel is fully booted.
     *
     * @param  Module  $module  The module
     * @param  array<string>  $seederFiles  The seeder files to run
     * @return array<string> The seeder classes run
     */
    private function runSeedersIfPossible(Module $module, array $seederFiles, bool $enabling): array
    {
        try {
            // Check if we're in a Laravel application context
            if (! function_exists('app')) {
                return [];
            }

            $app = app();

            // Check if the application has the db bound (indicates full boot)
            if (! $app->bound('db')) {
                return [];
            }

            $executed = [];
            $namespace = $module->namespace().'\\Database\\Seeders\\';

            // Pre-load all seeder classes first to ensure cross-references work
            // (e.g., GameSystemsSeeder calling PublishersSeeder via $this->call())
            $validSeeders = [];
            foreach ($seederFiles as $seederFile) {
                $className = pathinfo($seederFile, PATHINFO_FILENAME);
                $fullClassName = $namespace.$className;

                // Load the class if not already loaded
                $this->loadSeederClass($seederFile, $fullClassName);

                // Skip if class doesn't exist after loading
                if (! class_exists($fullClassName)) {
                    Log::warning("Seeder class {$fullClassName} not found in {$seederFile}");

                    continue;
                }

                // Skip if not a seeder
                if (! is_subclass_of($fullClassName, Seeder::class)) {
                    Log::warning("Class {$fullClassName} is not a Seeder");

                    continue;
                }

                $validSeeders[] = $fullClassName;
            }

            $alreadyExecuted = ModuleSeederHistoryModel::getExecutedSeeders($module->name()->value);
            $pending = array_values(array_diff($validSeeders, $alreadyExecuted));

            if ($pending === []) {
                return [];
            }

            if (! $this->provideModule($module, $enabling)) {
                Log::warning("Seeders of module {$module->name()->value} left pending: it is not enabled, they run when it is", [
                    'seeders' => $pending,
                ]);

                return [];
            }

            // Now run the valid seeders that have not run yet (all loaded above, so they can call each other)
            foreach ($pending as $fullClassName) {
                $seeder = $app->make($fullClassName);
                $this->executeSeeder($seeder);
                ModuleSeederHistoryModel::markExecuted($module->name()->value, $fullClassName);
                $executed[] = $fullClassName;

                Log::info("Ran seeder: {$fullClassName}");
            }

            return $executed;
        } catch (\Throwable $e) {
            // Only catch and suppress errors for initial bootstrapping issues
            // (missing app, db not bound), not seeder execution errors
            if (! function_exists('app') || ! app()->bound('db')) {
                return [];
            }

            // Re-throw seeder execution errors so they're visible
            throw $e;
        }
    }

    /**
     * Make sure the module's provider is loaded before its seeders run; false when the module
     * may not be booted in this process (see seed()).
     */
    private function provideModule(Module $module, bool $enabling): bool
    {
        $name = $module->name()->value;

        if ($this->loader->isLoaded($name)) {
            return true;
        }

        if (! $enabling && ! $this->loader->loads($name)) {
            return false;
        }

        $this->loader->bootModule($module);

        return true;
    }

    /**
     * Forget which seeders ran, so they run again on the next install.
     * Only for when the data they created has been removed.
     */
    public function forget(ModuleName $moduleName): void
    {
        ModuleSeederHistoryModel::query()->where('module_name', $moduleName->value)->delete();
    }

    /**
     * Load a seeder class file if the class doesn't exist yet.
     */
    private function loadSeederClass(string $seederFile, string $fullClassName): void
    {
        if (! class_exists($fullClassName)) {
            require_once $seederFile;
        }
    }

    /**
     * Execute a seeder instance.
     */
    private function executeSeeder(Seeder $seeder): void
    {
        // @phpstan-ignore-next-line method.notFound (run() is expected by Laravel but not declared in base class)
        $seeder->run();
    }
}
