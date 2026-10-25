<?php

declare(strict_types=1);

namespace Tests\Support\Modules;

use App\Application\Modules\Services\ModuleContextServiceInterface;
use App\Application\Modules\Services\ModuleManagerServiceInterface;
use App\Application\Modules\Services\ModuleNavigationRegistryInterface;
use App\Application\Modules\Services\ModulePermissionRegistryInterface;
use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Modules\ModuleLoader;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Translation\Translator;
use Tests\Support\Time\UsesUtcStorage;
use Tests\TestCase;

/**
 * Base test case for module tests.
 *
 * Provides utility methods for testing modules including
 * module discovery, enabling/disabling, and registry access.
 */
abstract class ModuleTestCase extends TestCase
{
    use InteractsWithModules;
    use LazilyRefreshDatabase;
    use UsesUtcStorage;

    /**
     * The module name being tested.
     */
    protected ?string $moduleName = null;

    /**
     * Whether to auto-discover and enable the module before each test.
     */
    protected bool $autoEnableModule = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear registries before each test
        $this->clearPermissionRegistry();
        $this->clearNavigationRegistry();

        // Auto-enable module if configured
        $moduleName = $this->moduleName;

        if ($this->autoEnableModule && $moduleName !== null) {
            $this->discoverModules();
            $this->enableModule($moduleName);
        }
    }

    protected function tearDown(): void
    {
        // Clean up module context
        $this->clearModuleContext();

        parent::tearDown();
    }

    /**
     * Get the module manager service.
     */
    protected function moduleManager(): ModuleManagerServiceInterface
    {
        return app(ModuleManagerServiceInterface::class);
    }

    /**
     * Get the module context service.
     */
    protected function moduleContext(): ModuleContextServiceInterface
    {
        return app(ModuleContextServiceInterface::class);
    }

    /**
     * Get the permission registry.
     */
    protected function permissionRegistry(): ModulePermissionRegistryInterface
    {
        return app(ModulePermissionRegistryInterface::class);
    }

    /**
     * Get the navigation registry.
     */
    protected function navigationRegistry(): ModuleNavigationRegistryInterface
    {
        return app(ModuleNavigationRegistryInterface::class);
    }

    /**
     * Discover all modules in the modules directory.
     */
    protected function discoverModules(): void
    {
        $this->moduleManager()->discover();
    }

    /**
     * Enable a module by name and boot its service provider.
     *
     * This method enables the module in the database AND boots its service provider,
     * which is necessary for tests since module boot happens before test setUp().
     * Translations loaded before the provider registered the module's namespace are
     * dropped, so the module's texts resolve from the first test of a process on.
     */
    protected function enableModule(string $moduleName): Module
    {
        $module = $this->moduleManager()->enable(new ModuleName($moduleName));

        // Boot the module's service provider since app boot already happened
        $moduleLoader = app(ModuleLoader::class);
        $moduleLoader->bootModule($module);

        // The first test of a process runs migrate:fresh, and a module migration (or a seeder run
        // by enable()) may translate a module key before the namespace exists: the translator then
        // caches that group as empty. Drop every loaded group; each one is read again on next use
        $this->app->make(Translator::class)->setLoaded([]);

        // Routes named after being added (Route::get()->name()) only reach the lookups when
        // they are refreshed, which the framework does once while booting: do it again
        $routes = app('router')->getRoutes();
        $routes->refreshNameLookups();
        $routes->refreshActionLookups();

        return $module;
    }

    /**
     * Disable a module by name.
     */
    protected function disableModule(string $moduleName): Module
    {
        return $this->moduleManager()->disable(new ModuleName($moduleName));
    }

    /**
     * Find a module by name.
     */
    protected function findModule(string $moduleName): ?Module
    {
        return $this->moduleManager()->find(new ModuleName($moduleName));
    }

    /**
     * Set the current module context.
     */
    protected function setModuleContext(string $moduleName): void
    {
        $this->moduleContext()->setCurrent($moduleName);
    }

    /**
     * Clear the current module context.
     */
    protected function clearModuleContext(): void
    {
        $this->moduleContext()->clearCurrent();
    }

    /**
     * Clear the permission registry.
     */
    protected function clearPermissionRegistry(): void
    {
        $this->permissionRegistry()->clear();
    }

    /**
     * Clear the navigation registry.
     */
    protected function clearNavigationRegistry(): void
    {
        $this->navigationRegistry()->clear();
    }

    /**
     * Assert a module is enabled.
     */
    protected function assertModuleEnabled(string $moduleName): void
    {
        $module = $this->findModule($moduleName);
        $this->assertNotNull($module, "Module '{$moduleName}' not found.");
        $this->assertTrue($module->isEnabled(), "Module '{$moduleName}' is not enabled.");
    }

    /**
     * Assert a module is disabled.
     */
    protected function assertModuleDisabled(string $moduleName): void
    {
        $module = $this->findModule($moduleName);
        $this->assertNotNull($module, "Module '{$moduleName}' not found.");
        $this->assertTrue($module->isDisabled(), "Module '{$moduleName}' is not disabled.");
    }

    /**
     * Assert a permission is registered.
     */
    protected function assertPermissionRegistered(string $name): void
    {
        $this->assertTrue(
            $this->permissionRegistry()->has($name),
            "Permission '{$name}' is not registered."
        );
    }

    /**
     * Assert a permission is not registered.
     */
    protected function assertPermissionNotRegistered(string $name): void
    {
        $this->assertFalse(
            $this->permissionRegistry()->has($name),
            "Permission '{$name}' should not be registered."
        );
    }
}
