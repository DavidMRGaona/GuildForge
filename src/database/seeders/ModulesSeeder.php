<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Application\Modules\Services\ModuleManagerServiceInterface;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use Illuminate\Database\Seeder;

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

        // Enable each discovered module
        foreach ($modules as $module) {
            // Skip if already enabled
            if ($module->isEnabled()) {
                continue;
            }

            // Mark as enabled and installed (migrations already ran via migrate:fresh)
            $module->enable();
            $module->markInstalled();
            $repository->save($module);
        }
    }
}
