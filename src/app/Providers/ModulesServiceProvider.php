<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Modules\Services\ModuleInstallerInterface;
use App\Console\Commands\Module\ModuleDisableCommand;
use App\Console\Commands\Module\ModuleDiscoverCommand;
use App\Console\Commands\Module\ModuleEnableCommand;
use App\Console\Commands\Module\ModuleListCommand;
use App\Console\Commands\Module\ModuleMakeCommand;
use App\Console\Commands\Module\ModuleMakeControllerCommand;
use App\Console\Commands\Module\ModuleMakeDtoCommand;
use App\Console\Commands\Module\ModuleMakeEntityCommand;
use App\Console\Commands\Module\ModuleMakeFilamentResourceCommand;
use App\Console\Commands\Module\ModuleMakeMigrationCommand;
use App\Console\Commands\Module\ModuleMakeRequestCommand;
use App\Console\Commands\Module\ModuleMakeServiceCommand;
use App\Console\Commands\Module\ModuleMakeTestCommand;
use App\Console\Commands\Module\ModuleMakeVueComponentCommand;
use App\Console\Commands\Module\ModuleMakeVuePageCommand;
use App\Console\Commands\Module\ModuleMigrateCommand;
use App\Console\Commands\Module\ModulePublishAssetsCommand;
use App\Infrastructure\Modules\Services\ModuleInstaller;
use App\Modules\ModuleLoader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

final class ModulesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/modules.php',
            'modules'
        );

        $this->app->bind(ModuleInstallerInterface::class, ModuleInstaller::class);
    }

    public function boot(): void
    {
        // Register console commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                // Module management commands
                ModuleListCommand::class,
                ModuleDiscoverCommand::class,
                ModuleEnableCommand::class,
                ModuleDisableCommand::class,
                ModuleMigrateCommand::class,
                ModulePublishAssetsCommand::class,
                // Module scaffolding commands
                ModuleMakeCommand::class,
                ModuleMakeEntityCommand::class,
                ModuleMakeControllerCommand::class,
                ModuleMakeRequestCommand::class,
                ModuleMakeServiceCommand::class,
                ModuleMakeDtoCommand::class,
                ModuleMakeMigrationCommand::class,
                ModuleMakeTestCommand::class,
                ModuleMakeFilamentResourceCommand::class,
                ModuleMakeVuePageCommand::class,
                ModuleMakeVueComponentCommand::class,
            ]);
        }

        // Boot enabled modules (only when database is available)
        $this->bootEnabledModules();
    }

    private function bootEnabledModules(): void
    {
        try {
            // Skip module booting during migrations or when a database doesn't exist
            if (! $this->isDatabaseAvailable()) {
                return;
            }

            $this->app->make(ModuleLoader::class)->boot();
        } catch (\Throwable $e) {
            // Log the error for debugging (uncomment in development)
            logger()->error('[ModulesServiceProvider] Module boot failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function isDatabaseAvailable(): bool
    {
        try {
            DB::connection()->getPdo();

            // Also check if the modules table exists (needed for fresh installs)
            if (! Schema::hasTable('modules')) {
                return false;
            }

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
