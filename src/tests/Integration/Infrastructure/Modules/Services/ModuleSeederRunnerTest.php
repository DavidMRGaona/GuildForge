<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Modules\Services;

use App\Application\Modules\Services\ModuleManagerServiceInterface;
use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Enums\ModuleStatus;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Infrastructure\Modules\Services\ModuleSeederRunner;
use App\Infrastructure\Persistence\Eloquent\Models\ModuleModel;
use App\Infrastructure\Updates\Persistence\Eloquent\Models\ModuleSeederHistoryModel;
use App\Modules\ModuleLoader;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Module seeders run once per installation: re-running them on every update would
 * overwrite what admins edited (updateOrCreate) or bring back what they deleted.
 */
final class ModuleSeederRunnerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $modulesPath;

    private string $namespace;

    protected function setUp(): void
    {
        parent::setUp();

        // Unique namespace per test: seeder classes cannot be unloaded between tests
        $suffix = 'S'.str_replace('.', '', uniqid('', true));
        $this->namespace = "Modules\\SeedMod{$suffix}";
        $this->modulesPath = sys_get_temp_dir().'/gf-seed-'.bin2hex(random_bytes(8));
        config(['modules.path' => $this->modulesPath]);

        Schema::create('seedmod_items', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        File::ensureDirectoryExists($this->modulesPath.'/seed-mod/database/seeders');
        // A compatible manifest: only an enabled and compatible module is booted to seed it
        File::put($this->modulesPath.'/seed-mod/module.json', json_encode([
            'name' => 'seed-mod',
            'version' => '1.0.0',
            'storesUtc' => true,
            'namespace' => $this->namespace,
            'provider' => 'SeedModServiceProvider',
            'requires' => ['core' => '>=2.0'],
        ], JSON_THROW_ON_ERROR));
        $this->writeSeeder('ItemsSeeder', "DB::table('seedmod_items')->insert(['name' => 'default']);");

        ModuleModel::factory()->enabled()->create([
            'name' => 'seed-mod',
            'path' => $this->modulesPath.'/seed-mod',
            'namespace' => $this->namespace,
            'provider' => 'SeedModServiceProvider',
            'installed_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->modulesPath);
        parent::tearDown();
    }

    public function test_it_runs_each_seeder_only_once(): void
    {
        $this->assertSame(["{$this->namespace}\\Database\\Seeders\\ItemsSeeder"], $this->runner()->seed($this->module()));
        $this->assertSame([], $this->runner()->seed($this->module()));

        $this->assertSame(1, DB::table('seedmod_items')->count());
    }

    public function test_it_runs_seeders_added_by_a_later_version(): void
    {
        $this->runner()->seed($this->module());
        $this->writeSeeder('ExtraSeeder', "DB::table('seedmod_items')->insert(['name' => 'extra']);");

        $this->assertSame(["{$this->namespace}\\Database\\Seeders\\ExtraSeeder"], $this->runner()->seed($this->module()));
        $this->assertSame(2, DB::table('seedmod_items')->count());
    }

    public function test_a_failing_seeder_is_not_recorded_so_it_runs_again(): void
    {
        $this->writeSeeder('BrokenSeeder', "throw new \\RuntimeException('broken seeder');");

        try {
            $this->runner()->seed($this->module());
            $this->fail('The failing seeder should have thrown');
        } catch (\RuntimeException $e) {
            $this->assertSame('broken seeder', $e->getMessage());
        }

        $this->assertFalse(ModuleSeederHistoryModel::wasExecuted('seed-mod', "{$this->namespace}\\Database\\Seeders\\BrokenSeeder"));
    }

    public function test_forgetting_a_module_lets_its_seeders_run_again(): void
    {
        $this->runner()->seed($this->module());

        $this->runner()->forget(ModuleName::fromString('seed-mod'));

        $this->assertSame(["{$this->namespace}\\Database\\Seeders\\ItemsSeeder"], $this->runner()->seed($this->module()));
    }

    /**
     * A seeder is module code like any other: it may resolve what the module's provider binds,
     * also when the module is enabled for the first time and its provider has not booted yet.
     */
    public function test_a_seeder_can_resolve_what_the_module_provider_binds(): void
    {
        $this->writeProvider();
        $this->writeSeeder('NamedItemsSeeder', "DB::table('seedmod_items')->insert(['name' => app(\\{$this->namespace}\\Contracts\\ItemName::class)->value()]);");

        $this->assertFalse($this->loader()->isLoaded('seed-mod'));

        $this->runner()->seed($this->module());

        $this->assertTrue($this->loader()->isLoaded('seed-mod'));
        $this->assertSame(1, DB::table('seedmod_items')->where('name', 'from provider')->count());
    }

    public function test_the_provider_is_not_booted_when_no_seeder_is_pending(): void
    {
        $this->writeProvider();
        ModuleSeederHistoryModel::markExecuted('seed-mod', "{$this->namespace}\\Database\\Seeders\\ItemsSeeder");

        $this->assertSame([], $this->runner()->seed($this->module()));

        $this->assertFalse($this->loader()->isLoaded('seed-mod'));
    }

    /**
     * A long-running process (a queue worker) would keep a disabled module's listeners, routes
     * and slots: its seeders wait until it is enabled.
     */
    public function test_a_disabled_module_is_not_booted_and_its_seeders_stay_pending(): void
    {
        $this->writeProvider();
        ModuleModel::query()->where('name', 'seed-mod')->update(['status' => ModuleStatus::Disabled->value]);
        $log = Log::spy();

        $this->assertSame([], $this->runner()->seed($this->module()));

        $this->assertFalse($this->loader()->isLoaded('seed-mod'));
        $this->assertSame(0, DB::table('seedmod_items')->count());
        $this->assertFalse(ModuleSeederHistoryModel::wasExecuted('seed-mod', "{$this->namespace}\\Database\\Seeders\\ItemsSeeder"));
        $log->shouldHaveReceived('warning')->withArgs(static fn (string $message): bool => str_contains($message, 'left pending'))->once();
    }

    public function test_a_module_being_enabled_is_booted_to_run_its_seeders(): void
    {
        $this->writeProvider();
        ModuleModel::query()->where('name', 'seed-mod')->update(['status' => ModuleStatus::Disabled->value]);

        $this->assertCount(1, $this->runner()->seed($this->module(), enabling: true));

        $this->assertTrue($this->loader()->isLoaded('seed-mod'));
    }

    public function test_enabling_an_installed_module_runs_the_seeders_left_pending_while_it_was_disabled(): void
    {
        $this->writeProvider();
        ModuleModel::query()->where('name', 'seed-mod')->update(['status' => ModuleStatus::Disabled->value]);
        $this->runner()->seed($this->module());

        $this->app->make(ModuleManagerServiceInterface::class)->enable(ModuleName::fromString('seed-mod'));

        $this->assertSame(1, DB::table('seedmod_items')->count());
        $this->assertTrue(ModuleSeederHistoryModel::wasExecuted('seed-mod', "{$this->namespace}\\Database\\Seeders\\ItemsSeeder"));
    }

    /**
     * ModuleTestCase boots the module after enabling it, and enabling may already have booted it
     * to run its seeders: the second boot must not register its hooks again.
     */
    public function test_booting_a_loaded_module_again_keeps_its_provider(): void
    {
        $this->writeProvider();
        $this->runner()->seed($this->module());
        $provider = $this->loader()->getProvider('seed-mod');

        $this->loader()->bootModule($this->module());

        $this->assertSame($provider, $this->loader()->getProvider('seed-mod'));
    }

    private function loader(): ModuleLoader
    {
        return $this->app->make(ModuleLoader::class);
    }

    private function runner(): ModuleSeederRunner
    {
        return $this->app->make(ModuleSeederRunner::class);
    }

    private function module(): Module
    {
        $module = $this->app->make(ModuleRepositoryInterface::class)->findByName(ModuleName::fromString('seed-mod'));
        $this->assertNotNull($module);

        return $module;
    }

    private function writeProvider(): void
    {
        File::ensureDirectoryExists($this->modulesPath.'/seed-mod/src/Contracts');

        File::put($this->modulesPath.'/seed-mod/src/Contracts/ItemName.php', <<<PHP
<?php

namespace {$this->namespace}\\Contracts;

interface ItemName
{
    public function value(): string;
}
PHP);

        File::put($this->modulesPath.'/seed-mod/src/SeedModServiceProvider.php', <<<PHP
<?php

namespace {$this->namespace};

use App\\Modules\\ModuleServiceProvider;
use {$this->namespace}\\Contracts\\ItemName;

class SeedModServiceProvider extends ModuleServiceProvider
{
    public function moduleName(): string
    {
        return 'seed-mod';
    }

    public function register(): void
    {
        parent::register();

        \$this->app->bind(ItemName::class, fn (): ItemName => new class implements ItemName
        {
            public function value(): string
            {
                return 'from provider';
            }
        });
    }
}
PHP);
    }

    private function writeSeeder(string $class, string $body): void
    {
        File::put($this->modulesPath."/seed-mod/database/seeders/{$class}.php", <<<PHP
<?php

namespace {$this->namespace}\\Database\\Seeders;

use Illuminate\\Database\\Seeder;
use Illuminate\\Support\\Facades\\DB;

class {$class} extends Seeder
{
    public function run(): void
    {
        {$body}
    }
}
PHP);
    }
}
