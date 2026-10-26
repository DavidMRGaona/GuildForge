<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Modules\Listeners;

use App\Domain\Modules\Enums\ModuleStatus;
use App\Domain\Modules\Events\ModuleEnabled;
use App\Infrastructure\Modules\Listeners\BuildModuleAssetsOnEnabled;
use App\Infrastructure\Persistence\Eloquent\Models\ModuleModel;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class BuildModuleAssetsOnEnabledTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $testModulesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testModulesPath = sys_get_temp_dir().'/guildforge-test-modules-'.bin2hex(random_bytes(8));
        mkdir($this->testModulesPath, 0755, true);
        config(['modules.path' => $this->testModulesPath]);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->testModulesPath);
        parent::tearDown();
    }

    public function test_listener_is_queued_outside_tests(): void
    {
        // Dispatched on its own dispatcher: the app's other ModuleEnabled listeners would
        // rebuild the framework caches once the environment is no longer "testing"
        Queue::fake();
        $dispatcher = (new Dispatcher($this->app))->setQueueResolver(fn (): QueueContract => Queue::connection());
        $dispatcher->listen(ModuleEnabled::class, BuildModuleAssetsOnEnabled::class);
        $this->app['env'] = 'production';

        try {
            $dispatcher->dispatch(new ModuleEnabled('fake-id', 'test-module'));
        } finally {
            $this->app['env'] = 'testing';
        }

        Queue::assertPushed(
            CallQueuedListener::class,
            fn (CallQueuedListener $job): bool => $job->class === BuildModuleAssetsOnEnabled::class,
        );
    }

    public function test_listener_is_registered_for_module_enabled(): void
    {
        Event::fake();

        Event::assertListening(ModuleEnabled::class, BuildModuleAssetsOnEnabled::class);
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

    public function test_enabling_a_module_in_tests_never_builds_its_assets(): void
    {
        // Module suites enable their module in every test, on a sync queue: a build there runs
        // npm in the module directory each time, and fails over and over in CI without host packages
        $this->createTestModuleWithComponentsAndConfig('test-module');
        $this->createModuleRecord('test-module');
        $log = Log::spy();

        event(new ModuleEnabled('fake-id', 'test-module'));

        $log->shouldNotHaveReceived('info', ['Building assets for module: test-module']);
        $this->assertDirectoryDoesNotExist($this->testModulesPath.'/test-module/node_modules');
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
            'path' => $this->testModulesPath.'/test-module',
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
            'path' => $this->testModulesPath.'/test-module',
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

    public function test_handle_builds_a_module_on_the_core_toolchain_without_package_json(): void
    {
        Process::fake();
        config(['module-build.required_vite_env' => []]);
        $this->createTestModuleWithComponents('test-module');
        file_put_contents(
            $this->testModulesPath.'/test-module/vite.config.ts',
            "import { defineModuleConfig } from '../../vite.module.config';\n\nexport default defineModuleConfig({ name: 'test-module', root: __dirname });\n",
        );
        $this->createModuleRecord('test-module');

        $this->app->make(BuildModuleAssetsOnEnabled::class)->handle(new ModuleEnabled('fake-id', 'test-module'));

        Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['sh', 'scripts/build-module.sh', 'test-module']);
    }

    public function test_handle_catches_exceptions_and_logs_error(): void
    {
        // The record outlives its directory: the builder throws before reaching npm,
        // so the test never launches a process
        $this->createModuleRecord('test-module');
        $log = Log::spy();

        $listener = $this->app->make(BuildModuleAssetsOnEnabled::class);
        $listener->handle(new ModuleEnabled('fake-id', 'test-module'));

        $log->shouldHaveReceived('error')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $message === 'BuildModuleAssetsOnEnabled: Exception while building assets for test-module'
                && $context['exception'] === "Module 'test-module' not found.");
    }

    private function createModuleRecord(string $name): void
    {
        ModuleModel::create([
            'name' => $name,
            'path' => $this->testModulesPath.'/'.$name,
            'display_name' => 'Test Module',
            'version' => '1.0.0',
            'description' => 'Test module',
            'author' => 'Test Author',
            'status' => ModuleStatus::Enabled->value,
            'requires' => null,
            'enabled_at' => now(),
        ]);
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
        ], JSON_THROW_ON_ERROR));
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
