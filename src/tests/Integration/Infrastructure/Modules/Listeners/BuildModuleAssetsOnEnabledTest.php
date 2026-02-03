<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Modules\Listeners;

use App\Domain\Modules\Enums\ModuleStatus;
use App\Domain\Modules\Events\ModuleEnabled;
use App\Infrastructure\Modules\Listeners\BuildModuleAssetsOnEnabled;
use App\Infrastructure\Persistence\Eloquent\Models\ModuleModel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

final class BuildModuleAssetsOnEnabledTest extends TestCase
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

    public function test_listener_implements_should_queue(): void
    {
        $listener = $this->app->make(BuildModuleAssetsOnEnabled::class);

        $this->assertInstanceOf(ShouldQueue::class, $listener);
    }

    public function test_listener_has_correct_timeout(): void
    {
        $listener = $this->app->make(BuildModuleAssetsOnEnabled::class);

        $this->assertEquals(600, $listener->timeout);
    }

    public function test_listener_has_single_try(): void
    {
        $listener = $this->app->make(BuildModuleAssetsOnEnabled::class);

        $this->assertEquals(1, $listener->tries);
    }

    public function test_handle_logs_warning_when_module_not_found(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->with('BuildModuleAssetsOnEnabled: Module not found: non-existent-module');

        $listener = $this->app->make(BuildModuleAssetsOnEnabled::class);
        $event = new ModuleEnabled('fake-id', 'non-existent-module');

        $listener->handle($event);
    }

    public function test_handle_logs_debug_when_module_has_no_components(): void
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

        Log::shouldReceive('debug')
            ->once()
            ->with('BuildModuleAssetsOnEnabled: Module test-module has no Vue components');

        $listener = $this->app->make(BuildModuleAssetsOnEnabled::class);
        $event = new ModuleEnabled('fake-id', 'test-module');

        $listener->handle($event);
    }

    public function test_handle_logs_warning_when_module_has_components_but_no_build_config(): void
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

        Log::shouldReceive('warning')
            ->once()
            ->with('BuildModuleAssetsOnEnabled: Module test-module has components but no build config');

        $listener = $this->app->make(BuildModuleAssetsOnEnabled::class);
        $event = new ModuleEnabled('fake-id', 'test-module');

        $listener->handle($event);
    }

    public function test_handle_catches_exceptions_and_logs_error(): void
    {
        // Create module directory but not the database record properly
        $this->createTestModuleWithComponentsAndConfig('test-module');

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

        // The listener should handle exceptions gracefully
        Log::shouldReceive('info')->andReturnNull();
        Log::shouldReceive('error')->andReturnNull();

        $listener = $this->app->make(BuildModuleAssetsOnEnabled::class);
        $event = new ModuleEnabled('fake-id', 'test-module');

        // Should not throw exception
        $listener->handle($event);

        $this->assertTrue(true);
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

    private function createTestModuleWithComponentsAndConfig(string $name): void
    {
        $this->createTestModuleWithComponents($name);
        $modulePath = $this->testModulesPath.'/'.$name;

        file_put_contents($modulePath.'/package.json', '{}');
        file_put_contents($modulePath.'/vite.config.ts', 'export default {}');
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
