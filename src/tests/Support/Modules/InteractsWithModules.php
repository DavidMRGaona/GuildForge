<?php

declare(strict_types=1);

namespace Tests\Support\Modules;

use App\Application\Modules\DTOs\NavigationItemDTO;
use App\Application\Modules\DTOs\PermissionDTO;
use App\Application\Modules\DTOs\ScaffoldResultDTO;
use App\Application\Modules\Services\ModuleNavigationRegistryInterface;
use App\Application\Modules\Services\ModulePermissionRegistryInterface;
use App\Application\Modules\Services\ModuleScaffoldingServiceInterface;
use Illuminate\Support\Facades\File;

/**
 * Trait for tests that need to interact with the module system.
 *
 * Provides utility methods for creating test modules, registering
 * permissions and navigation items, and cleaning up after tests.
 */
trait InteractsWithModules
{
    /**
     * @var array<string> Modules created during tests that should be cleaned up.
     */
    private array $createdModules = [];

    /**
     * Get the scaffolding service.
     */
    protected function scaffoldingService(): ModuleScaffoldingServiceInterface
    {
        return app(ModuleScaffoldingServiceInterface::class);
    }

    /**
     * Create a test module.
     */
    protected function createTestModule(
        string $name,
        ?string $description = null,
        ?string $author = null,
    ): ScaffoldResultDTO {
        $result = $this->scaffoldingService()->createModule($name, $description, $author);

        if ($result->isSuccess()) {
            $this->createdModules[] = $name;
        }

        return $result;
    }

    /**
     * Create a test entity in a module.
     */
    protected function createTestEntity(
        string $module,
        string $name,
        bool $withMigration = false,
    ): ScaffoldResultDTO {
        return $this->scaffoldingService()->createEntity($module, $name, $withMigration);
    }

    /**
     * Create a test controller in a module.
     */
    protected function createTestController(
        string $module,
        string $name,
        string $type = 'default',
    ): ScaffoldResultDTO {
        return $this->scaffoldingService()->createController($module, $name, $type);
    }

    /**
     * Register a test permission.
     */
    protected function registerTestPermission(
        string $name,
        string $label,
        string $group = 'test',
        ?string $module = null,
    ): PermissionDTO {
        $permission = new PermissionDTO(
            name: $name,
            label: $label,
            group: $group,
            module: $module,
        );

        app(ModulePermissionRegistryInterface::class)->register($permission);

        return $permission;
    }

    /**
     * Register a test navigation item.
     */
    protected function registerTestNavigation(
        string $label,
        ?string $route = null,
        string $group = 'test',
        int $sort = 0,
        ?string $module = null,
    ): NavigationItemDTO {
        $item = new NavigationItemDTO(
            label: $label,
            route: $route,
            group: $group,
            sort: $sort,
            module: $module,
        );

        app(ModuleNavigationRegistryInterface::class)->register($item);

        return $item;
    }

    /**
     * Get the path to a test module.
     */
    protected function testModulePath(string $moduleName, string $path = ''): string
    {
        $basePath = config('modules.path', base_path('modules')).'/'.$moduleName;

        return $path !== '' ? $basePath.'/'.ltrim($path, '/') : $basePath;
    }

    /**
     * Assert a file exists in a test module.
     */
    protected function assertModuleFileExists(string $moduleName, string $path): void
    {
        $fullPath = $this->testModulePath($moduleName, $path);
        $this->assertFileExists($fullPath, "File '{$path}' does not exist in module '{$moduleName}'.");
    }

    /**
     * Assert a file does not exist in a test module.
     */
    protected function assertModuleFileDoesNotExist(string $moduleName, string $path): void
    {
        $fullPath = $this->testModulePath($moduleName, $path);
        $this->assertFileDoesNotExist($fullPath, "File '{$path}' should not exist in module '{$moduleName}'.");
    }

    /**
     * Assert a directory exists in a test module.
     */
    protected function assertModuleDirectoryExists(string $moduleName, string $path): void
    {
        $fullPath = $this->testModulePath($moduleName, $path);
        $this->assertDirectoryExists($fullPath, "Directory '{$path}' does not exist in module '{$moduleName}'.");
    }

    /**
     * Assert the scaffold result was successful.
     */
    protected function assertScaffoldSuccess(ScaffoldResultDTO $result): void
    {
        $this->assertTrue(
            $result->isSuccess(),
            "Scaffold failed: {$result->message}. Errors: ".implode(', ', $result->errors)
        );
    }

    /**
     * Assert the scaffold result failed.
     */
    protected function assertScaffoldFailure(ScaffoldResultDTO $result): void
    {
        $this->assertTrue(
            $result->isFailure(),
            "Scaffold should have failed but succeeded: {$result->message}"
        );
    }

    /**
     * Clean up test modules created during tests.
     */
    protected function cleanupTestModules(): void
    {
        $modulesPath = config('modules.path', base_path('modules'));

        foreach ($this->createdModules as $moduleName) {
            $modulePath = $modulesPath.'/'.$moduleName;
            if (is_dir($modulePath)) {
                File::deleteDirectory($modulePath);
            }
        }

        $this->createdModules = [];
    }

    /**
     * Clean up after each test if the trait is used.
     *
     * @after
     */
    protected function cleanupModulesAfterTest(): void
    {
        $this->cleanupTestModules();
    }
}
