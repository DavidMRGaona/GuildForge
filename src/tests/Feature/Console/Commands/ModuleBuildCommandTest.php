<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands;

use App\Domain\Modules\Enums\ModuleStatus;
use App\Infrastructure\Persistence\Eloquent\Models\ModuleModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class ModuleBuildCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $testModulesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testModulesPath = sys_get_temp_dir().'/guildforge-test-modules-'.uniqid();
        mkdir($this->testModulesPath, 0755, true);
        config(['modules.path' => $this->testModulesPath]);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->testModulesPath);
        parent::tearDown();
    }

    public function test_command_requires_module_name_or_all_flag(): void
    {
        $this->artisan('module:build')
            ->expectsOutput('Please specify a module name or use --all to build all modules.')
            ->assertExitCode(1);
    }

    public function test_command_fails_when_module_not_found(): void
    {
        $this->artisan('module:build', ['name' => 'non-existent'])
            ->expectsOutput('Module not found: non-existent')
            ->assertExitCode(1);
    }

    public function test_command_succeeds_when_module_has_no_components(): void
    {
        $this->createTestModule('test-module');

        ModuleModel::create([
            'name' => 'test-module',
            'display_name' => 'Test Module',
            'version' => '1.0.0',
            'description' => 'Test module',
            'author' => 'Test Author',
            'status' => ModuleStatus::Enabled->value,
            'requires' => null,
            'enabled_at' => now(),
        ]);

        $this->artisan('module:build', ['name' => 'test-module'])
            ->expectsOutput('Module test-module has no Vue components to build.')
            ->assertExitCode(0);
    }

    public function test_command_fails_when_module_has_components_but_no_build_config(): void
    {
        $this->createTestModuleWithComponents('test-module');

        ModuleModel::create([
            'name' => 'test-module',
            'display_name' => 'Test Module',
            'version' => '1.0.0',
            'description' => 'Test module',
            'author' => 'Test Author',
            'status' => ModuleStatus::Enabled->value,
            'requires' => null,
            'enabled_at' => now(),
        ]);

        $this->artisan('module:build', ['name' => 'test-module'])
            ->expectsOutput('Module test-module has Vue components but no build configuration.')
            ->assertExitCode(1);
    }

    public function test_command_with_all_flag_succeeds_when_no_modules(): void
    {
        $this->artisan('module:build', ['--all' => true])
            ->expectsOutput('No modules found.')
            ->assertExitCode(0);
    }

    public function test_command_with_all_flag_skips_modules_without_components(): void
    {
        $this->createTestModule('module-one');

        ModuleModel::create([
            'name' => 'module-one',
            'display_name' => 'Module One',
            'version' => '1.0.0',
            'description' => 'First module',
            'author' => 'Test Author',
            'status' => ModuleStatus::Enabled->value,
            'requires' => null,
            'enabled_at' => now(),
        ]);

        $this->artisan('module:build', ['--all' => true])
            ->expectsOutputToContain('Skipping module-one (no Vue components)')
            ->expectsOutputToContain('Build complete: 0 built, 1 skipped, 0 failed')
            ->assertExitCode(0);
    }

    public function test_command_with_all_flag_reports_modules_with_components_but_no_config(): void
    {
        $this->createTestModuleWithComponents('test-module');

        ModuleModel::create([
            'name' => 'test-module',
            'display_name' => 'Test Module',
            'version' => '1.0.0',
            'description' => 'Test module',
            'author' => 'Test Author',
            'status' => ModuleStatus::Enabled->value,
            'requires' => null,
            'enabled_at' => now(),
        ]);

        $this->artisan('module:build', ['--all' => true])
            ->expectsOutputToContain('Skipping test-module (no build configuration)')
            ->expectsOutputToContain('Build complete: 0 built, 1 skipped, 0 failed')
            ->assertExitCode(0);
    }

    private function createTestModule(string $name): void
    {
        $modulePath = $this->testModulesPath.'/'.$name;
        mkdir($modulePath, 0755, true);

        file_put_contents($modulePath.'/module.json', json_encode([
            'name' => $name,
            'version' => '1.0.0',
            'description' => 'Test module',
            'author' => 'Test Author',
        ]));
    }

    private function createTestModuleWithComponents(string $name): void
    {
        $this->createTestModule($name);
        $modulePath = $this->testModulesPath.'/'.$name;

        $componentsPath = $modulePath.'/resources/js/components';
        mkdir($componentsPath, 0755, true);
        file_put_contents($componentsPath.'/Test.vue', '<template><div>Test</div></template>');
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
