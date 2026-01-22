<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Modules\Services;

use App\Application\Modules\DTOs\ModuleManifestDTO;
use App\Infrastructure\Modules\Services\ModuleDiscoveryService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ModuleDiscoveryServiceTest extends TestCase
{
    private ModuleDiscoveryService $service;
    private string $testModulesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testModulesPath = sys_get_temp_dir() . '/runesword_test_modules_' . uniqid();
        $this->service = new ModuleDiscoveryService($this->testModulesPath);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->testModulesPath);

        parent::tearDown();
    }

    public function test_it_discovers_modules_from_directory(): void
    {
        // Arrange
        mkdir($this->testModulesPath, 0755, true);

        $moduleDir = $this->testModulesPath . '/test-module';
        mkdir($moduleDir, 0755, true);

        $manifest = [
            'name' => 'test-module',
            'version' => '1.0.0',
            'namespace' => 'Modules\\TestModule',
            'provider' => 'TestModuleServiceProvider',
            'description' => 'A test module',
            'author' => 'Test Author',
        ];

        file_put_contents(
            $moduleDir . '/module.json',
            json_encode($manifest, JSON_THROW_ON_ERROR)
        );

        // Act
        $modules = $this->service->discover();

        // Assert
        $this->assertCount(1, $modules);
        $this->assertInstanceOf(ModuleManifestDTO::class, $modules[0]);
        $this->assertEquals('test-module', $modules[0]->name);
        $this->assertEquals('1.0.0', $modules[0]->version);
    }

    public function test_it_returns_empty_collection_when_no_modules_directory(): void
    {
        // Arrange
        // Don't create the directory

        // Act
        $modules = $this->service->discover();

        // Assert
        $this->assertIsArray($modules);
        $this->assertCount(0, $modules);
    }

    public function test_it_returns_empty_collection_when_modules_directory_is_empty(): void
    {
        // Arrange
        mkdir($this->testModulesPath, 0755, true);

        // Act
        $modules = $this->service->discover();

        // Assert
        $this->assertIsArray($modules);
        $this->assertCount(0, $modules);
    }

    public function test_it_parses_module_json_correctly(): void
    {
        // Arrange
        mkdir($this->testModulesPath, 0755, true);

        $moduleDir = $this->testModulesPath . '/advanced-module';
        mkdir($moduleDir, 0755, true);

        $manifest = [
            'name' => 'advanced-module',
            'version' => '2.5.3',
            'namespace' => 'Modules\\AdvancedModule',
            'provider' => 'AdvancedModuleServiceProvider',
            'description' => 'An advanced test module',
            'author' => 'Advanced Author',
            'requires' => [
                'php' => '>=8.2',
                'laravel' => '^11.0',
            ],
            'dependencies' => [
                'base-module',
                'helper-module',
            ],
        ];

        file_put_contents(
            $moduleDir . '/module.json',
            json_encode($manifest, JSON_THROW_ON_ERROR)
        );

        // Act
        $modules = $this->service->discover();

        // Assert
        $this->assertCount(1, $modules);
        $module = $modules[0];
        $this->assertEquals('advanced-module', $module->name);
        $this->assertEquals('2.5.3', $module->version);
        $this->assertEquals('Modules\\AdvancedModule', $module->namespace);
        $this->assertEquals('AdvancedModuleServiceProvider', $module->provider);
        $this->assertEquals('An advanced test module', $module->description);
        $this->assertEquals('Advanced Author', $module->author);
        $this->assertIsArray($module->requires);
        $this->assertEquals('>=8.2', $module->requires['php']);
        $this->assertIsArray($module->dependencies);
        $this->assertContains('base-module', $module->dependencies);
    }

    public function test_it_skips_modules_without_module_json(): void
    {
        // Arrange
        mkdir($this->testModulesPath, 0755, true);

        // Module with module.json
        $validModuleDir = $this->testModulesPath . '/valid-module';
        mkdir($validModuleDir, 0755, true);
        file_put_contents(
            $validModuleDir . '/module.json',
            json_encode([
                'name' => 'valid-module',
                'version' => '1.0.0',
                'namespace' => 'Modules\\ValidModule',
                'provider' => 'ValidModuleServiceProvider',
            ], JSON_THROW_ON_ERROR)
        );

        // Module without module.json
        $invalidModuleDir = $this->testModulesPath . '/invalid-module';
        mkdir($invalidModuleDir, 0755, true);

        // Act
        $modules = $this->service->discover();

        // Assert
        $this->assertCount(1, $modules);
        $this->assertEquals('valid-module', $modules[0]->name);
    }

    public function test_it_throws_exception_for_invalid_module_json(): void
    {
        // Arrange
        mkdir($this->testModulesPath, 0755, true);

        $moduleDir = $this->testModulesPath . '/invalid-json-module';
        mkdir($moduleDir, 0755, true);

        // Write invalid JSON
        file_put_contents(
            $moduleDir . '/module.json',
            '{"name": "test", invalid json}'
        );

        // Assert
        $this->expectException(InvalidArgumentException::class);

        // Act
        $this->service->discover();
    }

    private function removeDirectory(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        if (!is_dir($path)) {
            unlink($path);
            return;
        }

        $items = array_diff(scandir($path) ?: [], ['.', '..']);
        foreach ($items as $item) {
            $itemPath = $path . '/' . $item;
            if (is_dir($itemPath)) {
                $this->removeDirectory($itemPath);
            } else {
                unlink($itemPath);
            }
        }

        rmdir($path);
    }
}
