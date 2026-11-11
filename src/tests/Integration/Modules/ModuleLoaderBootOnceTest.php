<?php

declare(strict_types=1);

namespace Tests\Integration\Modules;

use App\Application\Authorization\Services\PermissionRegistryInterface;
use App\Application\Modules\Services\ModuleSlotRegistryInterface;
use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Infrastructure\Persistence\Eloquent\Models\ModuleModel;
use App\Modules\ModuleLoader;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Enabling a module may boot it to run its seeders, and ModuleTestCase boots it again right
 * after: the second boot must leave a single copy of each hook (core#525).
 */
final class ModuleLoaderBootOnceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $modulesPath;

    private string $namespace;

    protected function setUp(): void
    {
        parent::setUp();

        // Unique namespace per test: provider classes cannot be unloaded between tests
        $this->namespace = 'Modules\\BootOnce'.str_replace('.', '', uniqid('S', true));
        $this->modulesPath = sys_get_temp_dir().'/gf-boot-once-'.bin2hex(random_bytes(8));
        config(['modules.path' => $this->modulesPath]);

        $this->writeModule();

        ModuleModel::factory()->enabled()->create([
            'name' => 'boot-once',
            'path' => $this->modulesPath.'/boot-once',
            'namespace' => $this->namespace,
            'provider' => 'BootOnceServiceProvider',
            'installed_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->modulesPath);
        parent::tearDown();
    }

    public function test_booting_a_module_twice_registers_each_hook_once(): void
    {
        $loader = $this->app->make(ModuleLoader::class);

        $loader->bootModule($this->module());
        $provider = $loader->getProvider('boot-once');
        $loader->bootModule($this->module());

        $this->assertSame($provider, $loader->getProvider('boot-once'));
        $this->assertCount(1, $this->app->make(ModuleSlotRegistryInterface::class)->forModule('boot-once'));
        $this->assertCount(1, $this->app->make(PermissionRegistryInterface::class)->forModule('boot-once'));
        $this->assertCount(1, Event::getListeners('boot-once.pinged'));
        $this->assertCount(1, array_filter(
            app('router')->getRoutes()->getRoutes(),
            static fn (Route $route): bool => $route->uri() === 'boot-once-ping',
        ));
    }

    private function module(): Module
    {
        $module = $this->app->make(ModuleRepositoryInterface::class)->findByName(ModuleName::fromString('boot-once'));
        $this->assertNotNull($module);

        return $module;
    }

    private function writeModule(): void
    {
        File::ensureDirectoryExists($this->modulesPath.'/boot-once/src');
        File::ensureDirectoryExists($this->modulesPath.'/boot-once/routes');

        File::put($this->modulesPath.'/boot-once/routes/web.php', <<<'PHP'
<?php

use Illuminate\Support\Facades\Route;

Route::get('boot-once-ping', fn (): string => 'pong');
PHP);

        File::put($this->modulesPath.'/boot-once/src/BootOnceServiceProvider.php', <<<PHP
<?php

namespace {$this->namespace};

use App\\Application\\Modules\\DTOs\\PermissionDTO;
use App\\Application\\Modules\\DTOs\\SlotRegistrationDTO;
use App\\Modules\\ModuleServiceProvider;
use Illuminate\\Support\\Facades\\Event;

class BootOnceServiceProvider extends ModuleServiceProvider
{
    public function moduleName(): string
    {
        return 'boot-once';
    }

    public function boot(): void
    {
        parent::boot();

        Event::listen('boot-once.pinged', static fn (): null => null);
    }

    public function registerSlots(): array
    {
        return [new SlotRegistrationDTO(slot: 'profile-sections', component: 'BootOncePanel', module: 'boot-once')];
    }

    public function registerPermissions(): array
    {
        return [new PermissionDTO(name: 'pings.view', label: 'Ver pings', group: 'boot-once', module: 'boot-once')];
    }
}
PHP);
    }
}
