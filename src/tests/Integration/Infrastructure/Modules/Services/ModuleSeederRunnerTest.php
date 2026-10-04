<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Modules\Services;

use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Infrastructure\Modules\Services\ModuleSeederRunner;
use App\Infrastructure\Persistence\Eloquent\Models\ModuleModel;
use App\Infrastructure\Updates\Persistence\Eloquent\Models\ModuleSeederHistoryModel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
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
        $this->modulesPath = sys_get_temp_dir().'/gf-seed-'.uniqid();
        config(['modules.path' => $this->modulesPath]);

        Schema::create('seedmod_items', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        File::ensureDirectoryExists($this->modulesPath.'/seed-mod/database/seeders');
        $this->writeSeeder('ItemsSeeder', "DB::table('seedmod_items')->insert(['name' => 'default']);");

        ModuleModel::factory()->enabled()->create([
            'name' => 'seed-mod',
            'path' => $this->modulesPath.'/seed-mod',
            'namespace' => $this->namespace,
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
