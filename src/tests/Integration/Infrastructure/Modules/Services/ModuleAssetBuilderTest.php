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
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

final class ModuleAssetBuilderTest extends TestCase
{
    use LazilyRefreshDatabase;

    private ModuleAssetBuilder $builder;

    /** Where the build script runs: it builds <root>/modules/<name> */
    private string $testBuildRoot;

    private string $testModulesPath;

    private string $testPublicPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testBuildRoot = sys_get_temp_dir().'/guildforge-test-root-'.bin2hex(random_bytes(8));
        $this->testModulesPath = $this->testBuildRoot.'/modules';
        $this->testPublicPath = sys_get_temp_dir().'/guildforge-test-public-'.bin2hex(random_bytes(8));

        mkdir($this->testModulesPath, 0755, true);
        mkdir($this->testPublicPath.'/build/modules', 0755, true);

        // Override paths
        config(['modules.path' => $this->testModulesPath]);
        $this->app->usePublicPath($this->testPublicPath);

        $this->builder = new ModuleAssetBuilder($this->testModulesPath, $this->testBuildRoot);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->testBuildRoot);
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

    public function test_has_build_config_returns_false_without_any_build_file(): void
    {
        $module = $this->createModule('test-module');
        $this->createModuleDirectory('test-module');

        $this->assertFalse($this->builder->hasBuildConfig($module));
    }

    public function test_has_build_config_returns_true_for_a_module_on_the_core_toolchain(): void
    {
        // Modules on defineModuleConfig have no package.json: they build with the core's Vite
        $module = $this->createModule('test-module');
        $this->createModuleOnTheCoreToolchain('test-module');

        $this->assertTrue($this->builder->hasBuildConfig($module));
    }

    public function test_has_components_returns_true_when_only_pages_exist(): void
    {
        $module = $this->createModule('test-module');
        $this->createModuleDirectory('test-module');
        mkdir($this->testModulesPath.'/test-module/resources/js/pages', 0755, true);
        file_put_contents($this->testModulesPath.'/test-module/resources/js/pages/Index.vue', '<template><div /></template>');

        $this->assertTrue($this->builder->hasComponents($module));
    }

    public function test_build_runs_the_core_build_script_for_a_module_on_the_core_toolchain(): void
    {
        Process::fake();
        config(['module-build.required_vite_env' => [], 'module-build.vite_env' => ['VITE_APP_NAME' => 'Example']]);
        $module = $this->createModule('test-module');
        $this->createModuleOnTheCoreToolchain('test-module');
        $this->createModuleWithVueComponent('test-module', 'Test.vue');

        $this->assertTrue($this->builder->build($module, true));

        Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['sh', 'scripts/build-module.sh', 'test-module']
            && $process->path === $this->testBuildRoot
            && $process->environment === ['VITE_APP_NAME' => 'Example']);
        Process::assertRanTimes(fn (PendingProcess $process): bool => true, 1);
    }

    public function test_build_installs_the_libraries_of_a_module_on_the_core_toolchain_first(): void
    {
        Process::fake();
        config(['module-build.required_vite_env' => []]);
        $module = $this->createModule('test-module');
        $this->createModuleOnTheCoreToolchain('test-module');
        $this->createModuleWithVueComponent('test-module', 'Test.vue');
        file_put_contents($this->testModulesPath.'/test-module/package.json', '{"dependencies": {}}');

        $this->assertTrue($this->builder->build($module, true));

        Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['npm', 'ci', '--no-audit', '--no-fund']
            && $process->path === $this->testModulesPath.'/test-module');
        Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['sh', 'scripts/build-module.sh', 'test-module']);
    }

    public function test_build_refuses_a_module_with_its_own_toolchain(): void
    {
        // The core no longer runs a module's own Vite: it must move to defineModuleConfig
        Process::fake();
        config(['module-build.required_vite_env' => []]);
        $module = $this->createModule('test-module');
        $this->createModuleWithItsOwnToolchain('test-module');
        $this->createModuleWithVueComponent('test-module', 'Test.vue');

        $this->assertFalse($this->builder->build($module, true));

        Process::assertNothingRan();
    }

    public function test_build_refuses_a_module_outside_the_directory_the_build_script_builds(): void
    {
        // The script builds <root>/modules/<name>: a module elsewhere would install its
        // libraries in one directory and build another
        Process::fake();
        config(['module-build.required_vite_env' => []]);
        $otherModulesPath = sys_get_temp_dir().'/guildforge-test-other-modules-'.bin2hex(random_bytes(8));
        $this->testModulesPath = $otherModulesPath;
        $module = $this->createModule('test-module');
        $this->createModuleOnTheCoreToolchain('test-module');
        $this->createModuleWithVueComponent('test-module', 'Test.vue');
        file_put_contents($otherModulesPath.'/test-module/package.json', '{"dependencies": {}}');

        try {
            $builder = new ModuleAssetBuilder($otherModulesPath, $this->testBuildRoot);

            $this->assertFalse($builder->build($module, true));

            Process::assertNothingRan();
        } finally {
            $this->removeDirectory($otherModulesPath);
        }
    }

    public function test_build_fails_when_the_build_process_fails(): void
    {
        Process::fake(['*' => Process::result(errorOutput: 'Build failed', exitCode: 1)]);
        config(['module-build.required_vite_env' => []]);
        $module = $this->createModule('test-module');
        $this->createModuleOnTheCoreToolchain('test-module');
        $this->createModuleWithVueComponent('test-module', 'Test.vue');

        $this->assertFalse($this->builder->build($module, true));
    }

    public function test_has_build_config_returns_false_when_no_vite_config(): void
    {
        $module = $this->createModule('test-module');
        $this->createModuleDirectory('test-module');
        file_put_contents($this->testModulesPath.'/test-module/package.json', '{}');

        $this->assertFalse($this->builder->hasBuildConfig($module));
    }

    public function test_has_build_config_returns_false_for_a_module_with_its_own_toolchain(): void
    {
        $module = $this->createModule('test-module');
        $this->createModuleWithItsOwnToolchain('test-module');

        $this->assertFalse($this->builder->hasBuildConfig($module));
    }

    public function test_has_built_assets_returns_false_when_no_manifest(): void
    {
        $module = $this->createModule('test-module');

        $this->assertFalse($this->builder->hasBuiltAssets($module));
    }

    public function test_has_built_assets_returns_true_when_manifest_exists(): void
    {
        $module = $this->createModule('test-module');
        $manifestDir = public_path('build/modules/test-module');

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
        $this->createModuleOnTheCoreToolchain('test-module');

        // Should return true (skip building gracefully)
        $result = $this->builder->build($module);

        $this->assertTrue($result);
    }

    public function test_build_skips_when_assets_already_built_and_not_forced(): void
    {
        $module = $this->createModule('test-module-skip');
        $this->createModuleOnTheCoreToolchain('test-module-skip');
        $this->createModuleWithVueComponent('test-module-skip', 'Test.vue');

        // Create existing manifest in actual public path
        $manifestDir = public_path('build/modules/test-module-skip');

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

    public function test_vite_environment_forwards_configured_variables(): void
    {
        config(['module-build.vite_env' => [
            'VITE_CLOUDINARY_CLOUD_NAME' => 'test-cloud',
            'VITE_CLOUDINARY_PREFIX' => 'test-prefix',
        ]]);

        $this->assertSame([
            'VITE_CLOUDINARY_CLOUD_NAME' => 'test-cloud',
            'VITE_CLOUDINARY_PREFIX' => 'test-prefix',
        ], $this->builder->viteEnvironment());
    }

    public function test_vite_environment_omits_variables_without_value(): void
    {
        config(['module-build.vite_env' => [
            'VITE_CLOUDINARY_CLOUD_NAME' => 'test-cloud',
            'VITE_CLOUDINARY_PREFIX' => null,
            'VITE_EMPTY' => '',
        ]]);

        $this->assertSame(
            ['VITE_CLOUDINARY_CLOUD_NAME' => 'test-cloud'],
            $this->builder->viteEnvironment()
        );
    }

    public function test_missing_vite_env_vars_reports_required_variables_without_value(): void
    {
        config([
            'module-build.vite_env' => ['VITE_CLOUDINARY_CLOUD_NAME' => null],
            'module-build.required_vite_env' => ['VITE_CLOUDINARY_CLOUD_NAME'],
        ]);

        $this->assertSame(
            ['VITE_CLOUDINARY_CLOUD_NAME'],
            $this->builder->missingViteEnvVars()
        );
    }

    public function test_missing_vite_env_vars_is_empty_when_required_variables_are_present(): void
    {
        config([
            'module-build.vite_env' => ['VITE_CLOUDINARY_CLOUD_NAME' => 'test-cloud'],
            'module-build.required_vite_env' => ['VITE_CLOUDINARY_CLOUD_NAME'],
        ]);

        $this->assertSame([], $this->builder->missingViteEnvVars());
    }

    public function test_build_fails_when_required_vite_env_var_is_missing(): void
    {
        config([
            'module-build.vite_env' => ['VITE_CLOUDINARY_CLOUD_NAME' => null],
            'module-build.required_vite_env' => ['VITE_CLOUDINARY_CLOUD_NAME'],
        ]);

        $module = $this->createModule('test-module-env');
        $this->createModuleOnTheCoreToolchain('test-module-env');
        $this->createModuleWithVueComponent('test-module-env', 'Test.vue');

        // Must fail fast instead of producing assets with empty VITE_* values baked in
        $this->assertFalse($this->builder->build($module, true));
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

    private function createModuleWithItsOwnToolchain(string $name): void
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

    private function createModuleOnTheCoreToolchain(string $name): void
    {
        $this->createModuleDirectory($name);

        file_put_contents(
            $this->testModulesPath.'/'.$name.'/vite.config.ts',
            "import { defineModuleConfig } from '../../vite.module.config';\n\nexport default defineModuleConfig({ name: '{$name}', root: __dirname });\n",
        );
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
