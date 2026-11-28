<?php

declare(strict_types=1);

namespace Tests\Integration\Modules;

use App\Application\Bots\Services\BotRegistryInterface;
use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Infrastructure\Persistence\Eloquent\Models\ModuleModel;
use App\Modules\ModuleLoader;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** Booting a module registers its bots under its name (core#863) */
final class ModuleLoaderBotsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $modulesPath;

    private string $namespace;

    protected function setUp(): void
    {
        parent::setUp();

        // Unique namespace per test: provider classes cannot be unloaded between tests
        $this->namespace = 'Modules\\BotsBoot'.str_replace('.', '', uniqid('S', true));
        $this->modulesPath = sys_get_temp_dir().'/gf-bots-boot-'.bin2hex(random_bytes(8));
        config(['modules.path' => $this->modulesPath]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->modulesPath);
        parent::tearDown();
    }

    public function test_booting_a_module_registers_its_bots_under_its_name(): void
    {
        $this->writeModule(withBots: true, enabled: true);

        $this->app->make(ModuleLoader::class)->bootModule($this->module());

        $definition = $this->app->make(BotRegistryInterface::class)->find('bots-boot.publisher');
        $this->assertNotNull($definition);
        $this->assertSame('bots-boot', $definition->module);
        $this->assertSame($this->namespace.'\\PublisherBot', $definition->class);
    }

    public function test_a_module_without_bots_registers_none(): void
    {
        $this->writeModule(withBots: false, enabled: true);

        $this->app->make(ModuleLoader::class)->bootModule($this->module());

        $this->assertSame([], $this->app->make(BotRegistryInterface::class)->all());
    }

    public function test_a_disabled_module_is_not_booted_so_it_offers_no_bots(): void
    {
        $this->writeModule(withBots: true, enabled: false);

        // The same path the application takes: only enabled modules are booted
        $this->app->make(ModuleLoader::class)->boot();

        $this->assertNull($this->app->make(BotRegistryInterface::class)->find('bots-boot.publisher'));
    }

    private function module(): Module
    {
        $module = $this->app->make(ModuleRepositoryInterface::class)->findByName(ModuleName::fromString('bots-boot'));
        $this->assertNotNull($module);

        return $module;
    }

    private function writeModule(bool $withBots, bool $enabled): void
    {
        File::ensureDirectoryExists($this->modulesPath.'/bots-boot/src');

        $hook = $withBots ? <<<'PHP'

    public function registerBots(): array
    {
        return [PublisherBot::class];
    }
PHP : '';

        File::put($this->modulesPath.'/bots-boot/src/BotsBootServiceProvider.php', <<<PHP
<?php

namespace {$this->namespace};

use App\\Modules\\ModuleServiceProvider;

class BotsBootServiceProvider extends ModuleServiceProvider
{
    public function moduleName(): string
    {
        return 'bots-boot';
    }
{$hook}
}
PHP);

        File::put($this->modulesPath.'/bots-boot/src/PublisherBot.php', <<<PHP
<?php

namespace {$this->namespace};

use Tests\\Support\\Bots\\FakeBot;

class PublisherBot extends FakeBot
{
    public function key(): string
    {
        return 'bots-boot.publisher';
    }
}
PHP);

        $factory = ModuleModel::factory();
        ($enabled ? $factory->enabled() : $factory->disabled())->create([
            'name' => 'bots-boot',
            'path' => $this->modulesPath.'/bots-boot',
            'namespace' => $this->namespace,
            'provider' => 'BotsBootServiceProvider',
            'installed_at' => now(),
        ]);
    }
}
