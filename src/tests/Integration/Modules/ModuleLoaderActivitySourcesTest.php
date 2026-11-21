<?php

declare(strict_types=1);

namespace Tests\Integration\Modules;

use App\Application\Activity\Services\ActivitySourceRegistryInterface;
use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Infrastructure\Persistence\Eloquent\Models\ModuleModel;
use App\Modules\ModuleLoader;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** Booting a module registers its activity sources under its name (core#774) */
final class ModuleLoaderActivitySourcesTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $modulesPath;

    private string $namespace;

    protected function setUp(): void
    {
        parent::setUp();

        // Unique namespace per test: provider classes cannot be unloaded between tests
        $this->namespace = 'Modules\\ActivityBoot'.str_replace('.', '', uniqid('S', true));
        $this->modulesPath = sys_get_temp_dir().'/gf-activity-boot-'.bin2hex(random_bytes(8));
        config(['modules.path' => $this->modulesPath]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->modulesPath);
        parent::tearDown();
    }

    public function test_booting_a_module_registers_its_activity_sources_under_its_name(): void
    {
        $this->writeModule(withSources: true);

        $this->app->make(ModuleLoader::class)->bootModule($this->module());

        $registration = $this->app->make(ActivitySourceRegistryInterface::class)->find('activity-boot.session');
        $this->assertNotNull($registration);
        $this->assertSame('activity-boot', $registration->module);
        $this->assertSame($this->namespace.'\\SessionActivitySource', $registration->class);
    }

    public function test_a_module_without_activity_sources_registers_none(): void
    {
        $this->writeModule(withSources: false);

        $this->app->make(ModuleLoader::class)->bootModule($this->module());

        $this->assertSame([], $this->app->make(ActivitySourceRegistryInterface::class)->all());
    }

    private function module(): Module
    {
        $module = $this->app->make(ModuleRepositoryInterface::class)->findByName(ModuleName::fromString('activity-boot'));
        $this->assertNotNull($module);

        return $module;
    }

    private function writeModule(bool $withSources): void
    {
        File::ensureDirectoryExists($this->modulesPath.'/activity-boot/src');

        $hook = $withSources ? <<<'PHP'

    public function registerActivitySources(): array
    {
        return [SessionActivitySource::class];
    }
PHP : '';

        File::put($this->modulesPath.'/activity-boot/src/ActivityBootServiceProvider.php', <<<PHP
<?php

namespace {$this->namespace};

use App\\Modules\\ModuleServiceProvider;

class ActivityBootServiceProvider extends ModuleServiceProvider
{
    public function moduleName(): string
    {
        return 'activity-boot';
    }
{$hook}
}
PHP);

        File::put($this->modulesPath.'/activity-boot/src/SessionActivitySource.php', <<<PHP
<?php

namespace {$this->namespace};

use Tests\\Support\\Activity\\FakeActivitySource;

class SessionActivitySource extends FakeActivitySource
{
    public function type(): string
    {
        return 'activity-boot.session';
    }
}
PHP);

        ModuleModel::factory()->enabled()->create([
            'name' => 'activity-boot',
            'path' => $this->modulesPath.'/activity-boot',
            'namespace' => $this->namespace,
            'provider' => 'ActivityBootServiceProvider',
            'installed_at' => now(),
        ]);
    }
}
