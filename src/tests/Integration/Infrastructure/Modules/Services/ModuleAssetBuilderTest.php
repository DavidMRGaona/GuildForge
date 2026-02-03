<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Modules\Services;

use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Enums\ModuleStatus;
use App\Domain\Modules\ValueObjects\ModuleId;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Domain\Modules\ValueObjects\ModuleRequirements;
use App\Domain\Modules\ValueObjects\ModuleVersion;
use App\Infrastructure\Modules\Services\ModuleAssetBuilder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class ModuleAssetBuilderTest extends TestCase
{
    use LazilyRefreshDatabase;

    private ModuleAssetBuilder $builder;

    private string $testModulesPath;

    private string $testPublicPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testModulesPath = sys_get_temp_dir().'/guildforge-test-modules-'.uniqid();
        $this->testPublicPath = sys_get_temp_dir().'/guildforge-test-public-'.uniqid();

        mkdir($this->testModulesPath, 0755, true);
        mkdir($this->testPublicPath.'/build/modules', 0755, true);

        // Override paths
        config(['modules.path' => $this->testModulesPath]);
        $this->app->instance('path.public', $this->testPublicPath);

        $this->builder = new ModuleAssetBuilder($this->testModulesPath);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->testModulesPath);
        $this->removeDirectory($this->testPublicPath);
        parent::tearDown();
    }

    public function test_has_components_returns_false_when_no_components_directory(): void
    {
        $module = $this->createModule('test-module');
        $this->createModuleDirectory('test-module');

        $this->assertFalse($this->builder->hasComponents($module));
    }

    public function test_has_components_returns_false_when_components_directory_is_empty(): void
    {
        $module = $this->createModule('test-module');
        $this->createModuleDirectory('test-module');
        mkdir($this->testModulesPath.'/test-module/resources/js/components', 0755, true);

        $this->assertFalse($this->builder->hasComponents($module));
    }

    public function test_has_components_returns_true_when_vue_files_exist(): void
    {
        $module = $this->createModule('test-module');
        $this->createModuleWithVueComponent('test-module', 'TestComponent.vue');

        $this->assertTrue($this->builder->hasComponents($module));
    }

    public function test_has_components_returns_true_when_vue_files_exist_in_subdirectory(): void
    {
        $module = $this->createModule('test-module');
        $this->createModuleWithVueComponent('test-module', 'widgets/TestWidget.vue');

        $this->assertTrue($this->builder->hasComponents($module));
    }

    public function test_has_build_config_returns_false_when_no_package_json(): void
    {
        $module = $this->createModule('test-module');
        $this->createModuleDirectory('test-module');

        $this->assertFalse($this->builder->hasBuildConfig($module));
    }

    public function test_has_build_config_returns_false_when_no_vite_config(): void
    {
        $module = $this->createModule('test-module');
        $this->createModuleDirectory('test-module');
        file_put_contents($this->testModulesPath.'/test-module/package.json', '{}');

        $this->assertFalse($this->builder->hasBuildConfig($module));
    }

    public function test_has_build_config_returns_true_when_both_files_exist(): void
    {
        $module = $this->createModule('test-module');
        $this->createModuleWithBuildConfig('test-module');

        $this->assertTrue($this->builder->hasBuildConfig($module));
    }

    public function test_has_built_assets_returns_false_when_no_manifest(): void
    {
        $module = $this->createModule('test-module');

        $this->assertFalse($this->builder->hasBuiltAssets($module));
    }

    public function test_has_built_assets_returns_true_when_manifest_exists(): void
    {
        $module = $this->createModule('test-module');
        $manifestDir = public_path('build/modules/test-module/.vite');

        // Clean up before test
        if (is_dir($manifestDir)) {
            $this->removeDirectory(dirname($manifestDir));
        }

        mkdir($manifestDir, 0755, true);
        file_put_contents($manifestDir.'/manifest.json', '{}');

        try {
            $this->assertTrue($this->builder->hasBuiltAssets($module));
        } finally {
            // Clean up after test
            $this->removeDirectory(public_path('build/modules/test-module'));
        }
    }

    public function test_build_returns_true_when_no_build_config(): void
    {
        $module = $this->createModule('test-module');
        $this->createModuleDirectory('test-module');

        // Should return true (skip building gracefully)
        $result = $this->builder->build($module);

        $this->assertTrue($result);
    }

    public function test_build_returns_true_when_no_components(): void
    {
        $module = $this->createModule('test-module');
        $this->createModuleWithBuildConfig('test-module');

        // Should return true (skip building gracefully)
        $result = $this->builder->build($module);

        $this->assertTrue($result);
    }

    public function test_build_skips_when_assets_already_built_and_not_forced(): void
    {
        $module = $this->createModule('test-module-skip');
        $this->createModuleWithBuildConfig('test-module-skip');
        $this->createModuleWithVueComponent('test-module-skip', 'Test.vue');

        // Create existing manifest in actual public path
        $manifestDir = public_path('build/modules/test-module-skip/.vite');

        // Clean up before test
        if (is_dir($manifestDir)) {
            $this->removeDirectory(dirname($manifestDir));
        }

        mkdir($manifestDir, 0755, true);
        file_put_contents($manifestDir.'/manifest.json', '{}');

        try {
            // Should return true (skipped)
            $result = $this->builder->build($module, false);

            $this->assertTrue($result);
        } finally {
            // Clean up after test
            $this->removeDirectory(public_path('build/modules/test-module-skip'));
        }
    }

    private function createModule(string $name): Module
    {
        return new Module(
            id: ModuleId::generate(),
            name: ModuleName::fromString($name),
            displayName: ucfirst($name),
            description: 'Test module',
            version: ModuleVersion::fromString('1.0.0'),
            author: 'Test Author',
            requirements: ModuleRequirements::fromArray([]),
            status: ModuleStatus::Disabled,
            enabledAt: null,
            installedAt: null,
            createdAt: null,
            updatedAt: null,
            namespace: null,
            provider: null,
            path: $this->testModulesPath.'/'.$name,
        );
    }

    private function createModuleDirectory(string $name): void
    {
        $modulePath = $this->testModulesPath.'/'.$name;
        if (! is_dir($modulePath)) {
            mkdir($modulePath, 0755, true);
        }

        file_put_contents($modulePath.'/module.json', json_encode([
            'name' => $name,
            'version' => '1.0.0',
            'description' => 'Test module',
            'author' => 'Test Author',
        ]));
    }

    private function createModuleWithBuildConfig(string $name): void
    {
        $this->createModuleDirectory($name);
        $modulePath = $this->testModulesPath.'/'.$name;

        file_put_contents($modulePath.'/package.json', json_encode([
            'name' => '@guildforge/module-'.$name,
            'private' => true,
            'type' => 'module',
            'scripts' => [
                'build' => 'vite build',
            ],
        ]));

        file_put_contents($modulePath.'/vite.config.ts', 'export default {}');
    }

    private function createModuleWithVueComponent(string $name, string $componentPath): void
    {
        $this->createModuleDirectory($name);
        $modulePath = $this->testModulesPath.'/'.$name;

        $fullPath = $modulePath.'/resources/js/components/'.$componentPath;
        $dir = dirname($fullPath);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($fullPath, '<template><div>Test</div></template>');
    }

    private function removeDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $path.'/'.$item;
            if (is_dir($itemPath)) {
                $this->removeDirectory($itemPath);
            } else {
                unlink($itemPath);
            }
        }

        rmdir($path);
    }
}
