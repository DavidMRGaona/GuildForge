<?php

declare(strict_types=1);

namespace App\Providers;

use App\Console\Commands\Module\ModuleDisableCommand;
use App\Console\Commands\Module\ModuleDiscoverCommand;
use App\Console\Commands\Module\ModuleEnableCommand;
use App\Console\Commands\Module\ModuleListCommand;
use App\Console\Commands\Module\ModuleMigrateCommand;
use App\Modules\ModuleLoader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

final class ModulesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/modules.php',
            'modules'
        );
    }

    public function boot(): void
    {
        // Register console commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                ModuleListCommand::class,
                ModuleDiscoverCommand::class,
                ModuleEnableCommand::class,
                ModuleDisableCommand::class,
                ModuleMigrateCommand::class,
            ]);
        }

        // Boot enabled modules (only when database is available)
        $this->bootEnabledModules();
    }

    private function bootEnabledModules(): void
    {
        try {
            // Skip module booting during migrations or when a database doesn't exist
            if (!$this->isDatabaseAvailable()) {
                return;
            }

            $this->app->make(ModuleLoader::class)->boot();
        } catch (\Throwable) {
            // Silently fail during migrations, seeding, or when a database doesn't exist yet
            // This prevents errors during initial setup
        }
    }

    private function isDatabaseAvailable(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
