<?php

declare(strict_types=1);

namespace App\Modules;

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
        $basePath = config('modules.path') . '/' . $this->moduleName();

        return $path !== '' ? $basePath . '/' . ltrim($path, '/') : $basePath;
    }

    public function register(): void
    {
        $configPath = $this->modulePath('config/module.php');

        if (file_exists($configPath)) {
            $this->mergeConfigFrom($configPath, 'modules.' . $this->moduleName());
        }
    }

    public function boot(): void
    {
        $this->loadRoutes();
        $this->loadViews();
        $this->loadTranslations();
        $this->loadMigrations();
    }

    protected function loadRoutes(): void
    {
        $webRoutes = $this->modulePath('routes/web.php');
        $apiRoutes = $this->modulePath('routes/api.php');

        if (file_exists($webRoutes)) {
            $this->loadRoutesFrom($webRoutes);
        }

        if (file_exists($apiRoutes)) {
            $this->loadRoutesFrom($apiRoutes);
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
    // Future Integration (prepared but not implemented in Phase 1)
    // ==================

    /**
     * Register permissions provided by this module.
     *
     * @return array<string, string> ['permission.key' => 'Description']
     */
    public function registerPermissions(): array
    {
        return [];
    }

    /**
     * Register navigation items provided by this module.
     *
     * @return array<array{label: string, route: string, icon?: string}>
     */
    public function registerNavigation(): array
    {
        return [];
    }
}
