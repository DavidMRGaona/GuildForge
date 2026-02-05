<?php

declare(strict_types=1);

namespace App\Modules;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

abstract class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Get the module name (kebab-case).
     */
    abstract public function moduleName(): string;

    /**
     * Get the module's base path.
     */
    protected function modulePath(string $path = ''): string
    {
        $basePath = config('modules.path').'/'.$this->moduleName();

        return $path !== '' ? $basePath.'/'.ltrim($path, '/') : $basePath;
    }

    /**
     * Get the module's base path (public accessor).
     */
    public function getModulePath(): string
    {
        return $this->modulePath();
    }

    public function register(): void
    {
        $configPath = $this->modulePath('config/module.php');

        if (file_exists($configPath)) {
            $this->mergeConfigFrom($configPath, 'modules.'.$this->moduleName());
        }

        // Load module settings into Laravel config
        $settingsPath = $this->modulePath('config/settings.php');
        if (file_exists($settingsPath)) {
            $settings = require $settingsPath;
            config()->set("modules.settings.{$this->moduleName()}", $settings);
        }
    }

    public function boot(): void
    {
        $this->loadRoutes();
        $this->loadViews();
        $this->loadTranslations();
        $this->loadMigrations();
        $this->loadPolicies();
    }

    protected function loadRoutes(): void
    {
        $webRoutes = $this->modulePath('routes/web.php');
        $apiRoutes = $this->modulePath('routes/api.php');

        if (file_exists($webRoutes)) {
            \Illuminate\Support\Facades\Route::middleware('web')
                ->group($webRoutes);
        }

        if (file_exists($apiRoutes)) {
            \Illuminate\Support\Facades\Route::middleware('api')
                ->prefix('api')
                ->group($apiRoutes);
        }
    }

    protected function loadViews(): void
    {
        $viewsPath = $this->modulePath('resources/views');

        if (is_dir($viewsPath)) {
            $this->loadViewsFrom($viewsPath, $this->moduleName());
        }
    }

    protected function loadTranslations(): void
    {
        $langPath = $this->modulePath('lang');

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->moduleName());
        }
    }

    protected function loadMigrations(): void
    {
        $migrationsPath = $this->modulePath('database/migrations');

        if (is_dir($migrationsPath)) {
            $this->loadMigrationsFrom($migrationsPath);
        }
    }

    protected function loadPolicies(): void
    {
        foreach ($this->registerPolicies() as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }

    // ==================
    // Lifecycle Hooks
    // ==================

    /**
     * Called when the module is enabled.
     * Override to add custom enable logic.
     */
    public function onEnable(): void
    {
        // Override in subclass
    }

    /**
     * Called when the module is disabled.
     * Override to add custom disable logic.
     */
    public function onDisable(): void
    {
        // Override in subclass
    }

    // ==================
    // Module Integration
    // ==================

    /**
     * Register policies provided by this module.
     * Override in subclass to provide model policies.
     *
     * @return array<class-string, class-string> Map of model class to policy class
     */
    public function registerPolicies(): array
    {
        return [];
    }

    /**
     * Register Filament navigation groups provided by this module.
     * Groups will only be added if they don't already exist.
     *
     * @return array<string, array{icon?: string, sort?: int}> Map of group label to options
     */
    public function registerNavigationGroups(): array
    {
        return [];
    }

    /**
     * Register permissions provided by this module.
     *
     * @return array<\App\Application\Modules\DTOs\PermissionDTO>
     */
    public function registerPermissions(): array
    {
        return [];
    }

    /**
     * Register navigation items provided by this module.
     *
     * @return array<\App\Application\Modules\DTOs\NavigationItemDTO>
     */
    public function registerNavigation(): array
    {
        return [];
    }

    /**
     * Register slot components provided by this module.
     * Slots allow modules to inject Vue components into layout positions.
     *
     * @return array<\App\Application\Modules\DTOs\SlotRegistrationDTO>
     */
    public function registerSlots(): array
    {
        return [];
    }

    /**
     * Get the Filament form schema for module settings.
     * Override in subclass to provide configurable settings.
     *
     * @return array<\Filament\Forms\Components\Component>
     */
    public function getSettingsSchema(): array
    {
        return [];
    }

    /**
     * Register page prefixes provided by this module.
     * Allows module Vue pages to be resolved by Inertia.
     *
     * @return array<\App\Application\Modules\DTOs\PagePrefixDTO>
     */
    public function registerPagePrefixes(): array
    {
        return [];
    }

    /**
     * Register public routes for menu item configuration.
     * Allows module routes to appear in the menu item dropdown.
     *
     * @return array<\App\Application\Modules\DTOs\ModuleRouteDTO>
     */
    public function registerRoutes(): array
    {
        return [];
    }

    /**
     * Register Filament pages provided by this module.
     * Override in subclass to provide Filament pages.
     *
     * @return array<class-string<\Filament\Pages\Page>>
     */
    public function registerFilamentPages(): array
    {
        return [];
    }
}
