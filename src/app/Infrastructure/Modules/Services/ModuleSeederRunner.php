<?php

declare(strict_types=1);

namespace App\Infrastructure\Modules\Services;

use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Exceptions\ModuleNotFoundException;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

final readonly class ModuleSeederRunner
{
    public function __construct(
        private string $modulesPath,
    ) {
    }

    /**
     * Runs seeders for a module.
     *
     * @param  Module  $module  The module to run seeders for
     * @return int The number of seeders run
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

        $seedersPath = $modulePath.'/database/seeders';

        if (! is_dir($seedersPath)) {
            return 0;
        }

        $seederFiles = glob($seedersPath.'/*Seeder.php');

        if ($seederFiles === false || empty($seederFiles)) {
            return 0;
        }

        // Run seeders if Laravel is fully booted
        return $this->runSeedersIfPossible($module, $seederFiles);
    }

    /**
     * Run seeders if Laravel is fully booted.
     *
     * @param  Module  $module  The module
     * @param  array<string>  $seederFiles  The seeder files to run
     * @return int The number of seeders run
     */
    private function runSeedersIfPossible(Module $module, array $seederFiles): int
    {
        try {
            // Check if we're in a Laravel application context
            if (! function_exists('app')) {
                return 0;
            }

            $app = app();

            // Check if the application has the db bound (indicates full boot)
            if (! $app->bound('db')) {
                return 0;
            }

            $count = 0;
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

            // Now run all valid seeders
            foreach ($validSeeders as $fullClassName) {
                $seeder = $app->make($fullClassName);
                $this->executeSeeder($seeder);
                $count++;

                Log::info("Ran seeder: {$fullClassName}");
            }

            return $count;
        } catch (\Throwable $e) {
            // Only catch and suppress errors for initial bootstrapping issues
            // (missing app, db not bound), not seeder execution errors
            if (! function_exists('app') || ! app()->bound('db')) {
                return 0;
            }

            // Re-throw seeder execution errors so they're visible
            throw $e;
        }
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
