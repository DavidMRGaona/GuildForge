<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Application\Modules\Services\ModuleManagerServiceInterface;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Infrastructure\Modules\Services\ModuleSeederRunner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

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
     * Run all seeders for a module by delegating to ModuleSeederRunner.
     */
    private function runModuleSeeders(string $moduleName): void
    {
        $seederRunner = app(ModuleSeederRunner::class);
        $repository = app(ModuleRepositoryInterface::class);
        $module = $repository->findByName(new ModuleName($moduleName));

        if ($module === null) {
            return;
        }

        try {
            $seederRunner->run($module);
        } catch (\Throwable $e) {
            Log::warning("Failed to run seeders for module {$moduleName}: {$e->getMessage()}");
        }
    }
}
