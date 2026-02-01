<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Pages;

use App\Application\Modules\Services\ModuleManagerServiceInterface;
use App\Domain\Modules\Events\ModuleUninstalled;
use App\Filament\Pages\ModulesPage;
use App\Infrastructure\Persistence\Eloquent\Models\ModuleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

final class ModuleUninstallTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $modulesPath;

    private string $testId;

    protected function setUp(): void
    {
        parent::setUp();

        // Use unique ID for parallel test isolation
        $this->testId = uniqid('uninstall_', true);
        $this->modulesPath = storage_path("app/test-modules-uninstall-{$this->testId}");
        File::ensureDirectoryExists($this->modulesPath);
        config(['modules.path' => $this->modulesPath]);
    }

    protected function tearDown(): void
    {
        // Clean up unique directory for this test
        if (File::isDirectory($this->modulesPath)) {
            File::deleteDirectory($this->modulesPath);
        }

        parent::tearDown();
    }

    public function test_can_uninstall_disabled_module(): void
    {
        $user = UserModel::factory()->admin()->create();

        // Create module directory
        $modulePath = $this->modulesPath.'/test-module';
        File::ensureDirectoryExists($modulePath);
        File::put($modulePath.'/module.json', json_encode(['name' => 'test-module']));

        $module = ModuleModel::factory()->disabled()->create([
            'name' => 'test-module',
            'path' => $modulePath,
        ]);

        $this->actingAs($user);

        // Fake events and reset the singleton to pick up the fake dispatcher
        Event::fake([ModuleUninstalled::class]);

        // Force re-resolution of the service so it picks up the faked EventDispatcher
        $this->app->forgetInstance(ModuleManagerServiceInterface::class);

        Livewire::test(ModulesPage::class)
            ->call('uninstallModule', 'test-module')
            ->assertNotified();

        $this->assertDatabaseMissing('modules', [
            'id' => $module->id,
        ]);

        $this->assertFalse(File::isDirectory($modulePath));

        Event::assertDispatched(ModuleUninstalled::class, function ($event) {
            return $event->moduleName === 'test-module';
        });
    }

    public function test_cannot_uninstall_module_with_enabled_dependents(): void
    {
        $user = UserModel::factory()->admin()->create();

        // Create base module directory
        $baseModulePath = $this->modulesPath.'/base-module';
        File::makeDirectory($baseModulePath, 0755, true);
        File::put($baseModulePath.'/module.json', json_encode(['name' => 'base-module']));

        ModuleModel::factory()->disabled()->create([
            'name' => 'base-module',
            'path' => $baseModulePath,
        ]);

        ModuleModel::factory()->enabled()->create([
            'name' => 'dependent-module',
            'dependencies' => ['base-module'],
        ]);

        $this->actingAs($user);

        Livewire::test(ModulesPage::class)
            ->call('uninstallModule', 'base-module')
            ->assertNotified();

        // Module should still exist
        $this->assertDatabaseHas('modules', [
            'name' => 'base-module',
        ]);

        // Module directory should still exist
        $this->assertTrue(File::isDirectory($baseModulePath));
    }

    public function test_uninstall_removes_database_record(): void
    {
        $user = UserModel::factory()->admin()->create();

        $modulePath = $this->modulesPath.'/removable-module';
        File::makeDirectory($modulePath, 0755, true);
        File::put($modulePath.'/module.json', json_encode(['name' => 'removable-module']));

        $module = ModuleModel::factory()->disabled()->create([
            'name' => 'removable-module',
            'path' => $modulePath,
        ]);

        $this->actingAs($user);

        $this->assertDatabaseHas('modules', ['id' => $module->id]);

        Livewire::test(ModulesPage::class)
            ->call('uninstallModule', 'removable-module');

        $this->assertDatabaseMissing('modules', ['id' => $module->id]);
    }

    public function test_uninstall_removes_files(): void
    {
        $user = UserModel::factory()->admin()->create();

        $modulePath = $this->modulesPath.'/file-module';
        File::makeDirectory($modulePath.'/src', 0755, true);
        File::put($modulePath.'/module.json', json_encode(['name' => 'file-module']));
        File::put($modulePath.'/src/ServiceProvider.php', '<?php');

        ModuleModel::factory()->disabled()->create([
            'name' => 'file-module',
            'path' => $modulePath,
        ]);

        $this->assertTrue(File::isDirectory($modulePath));
        $this->assertTrue(File::exists($modulePath.'/module.json'));
        $this->assertTrue(File::exists($modulePath.'/src/ServiceProvider.php'));

        $this->actingAs($user);

        Livewire::test(ModulesPage::class)
            ->call('uninstallModule', 'file-module');

        $this->assertFalse(File::isDirectory($modulePath));
        $this->assertFalse(File::exists($modulePath.'/module.json'));
    }

    public function test_uninstall_dispatches_event(): void
    {
        $user = UserModel::factory()->admin()->create();

        $modulePath = $this->modulesPath.'/event-module';
        File::makeDirectory($modulePath, 0755, true);
        File::put($modulePath.'/module.json', json_encode(['name' => 'event-module']));

        ModuleModel::factory()->disabled()->create([
            'name' => 'event-module',
            'version' => '2.0.0',
            'path' => $modulePath,
        ]);

        $this->actingAs($user);

        // Fake events and reset the singleton to pick up the fake dispatcher
        Event::fake([ModuleUninstalled::class]);
        $this->app->forgetInstance(ModuleManagerServiceInterface::class);

        Livewire::test(ModulesPage::class)
            ->call('uninstallModule', 'event-module');

        Event::assertDispatched(ModuleUninstalled::class, function ($event) {
            return $event->moduleName === 'event-module'
                && $event->moduleVersion === '2.0.0';
        });
    }
}
