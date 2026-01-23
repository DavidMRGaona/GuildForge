<?php

declare(strict_types=1);

namespace Modules\TestSdk;

use App\Modules\ModuleServiceProvider;

final class TestSdkServiceProvider extends ModuleServiceProvider
{
    /**
     * Get the module name (kebab-case).
     */
    public function moduleName(): string
    {
        return 'test-sdk';
    }

    /**
     * Register module services.
     */
    public function register(): void
    {
        parent::register();

        $this->mergeConfigFrom(
            $this->modulePath('config/module.php'),
            'test_sdk'
        );
    }

    /**
     * Bootstrap module services.
     */
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * Called when the module is enabled.
     */
    public function onEnable(): void
    {
        // Perform setup tasks when module is enabled
    }

    /**
     * Called when the module is disabled.
     */
    public function onDisable(): void
    {
        // Perform cleanup tasks when module is disabled
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
}
