<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Application\Modules\Services\ModuleManagerServiceInterface;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * Discovers and enables all modules after a fresh migration.
 *
 * This seeder handles the module system state after migrate:fresh,
 * which drops the modules table and loses all module records.
 */
final class ModulesSeeder extends Seeder
{
    public function run(): void
    {
        $moduleManager = app(ModuleManagerServiceInterface::class);
        $repository = app(ModuleRepositoryInterface::class);

        // Discover all modules from filesystem
        $modules = $moduleManager->discover();

        // Enable each discovered module and run their seeders
        foreach ($modules as $module) {
            // Skip if already enabled
            if ($module->isEnabled()) {
                $this->runModuleSeeders($module->name()->toString());
                continue;
            }

            // Mark as enabled and installed (migrations already ran via migrate:fresh)
            $module->enable();
            $module->markInstalled();
            $repository->save($module);

            // Run module seeders
            $this->runModuleSeeders($module->name()->toString());
        }
    }

    /**
     * Run all seeders found in a module's Database/Seeders directory.
     */
    private function runModuleSeeders(string $moduleName): void
    {
        $modulePath = config('modules.path').'/'.$moduleName;
        $seedersPath = $modulePath.'/src/Database/Seeders';

        if (! is_dir($seedersPath)) {
            return;
        }

        // Get module namespace from module.json
        $moduleJsonPath = $modulePath.'/module.json';
        if (! file_exists($moduleJsonPath)) {
            return;
        }

        $moduleConfig = json_decode((string) File::get($moduleJsonPath), true);
        $namespace = $moduleConfig['namespace'] ?? null;

        if ($namespace === null) {
            return;
        }

        // Find all seeder files
        $seederFiles = File::glob($seedersPath.'/*Seeder.php');

        foreach ($seederFiles as $seederFile) {
            $className = pathinfo($seederFile, PATHINFO_FILENAME);
            $seederClass = $namespace.'\\Database\\Seeders\\'.$className;

            if (class_exists($seederClass)) {
                $this->call($seederClass);
            }
        }
    }
}
