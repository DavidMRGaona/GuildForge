<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands\Updates;

use App\Application\Updates\DTOs\HealthCheckResultDTO;
use App\Application\Updates\DTOs\PostUpdateReportDTO;
use App\Application\Updates\Services\ModuleHealthCheckerInterface;
use App\Infrastructure\Persistence\Eloquent\Models\ModuleModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\PendingCommand;
use Mockery;
use Tests\TestCase;

final class FinishModuleUpdateCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $modulesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modulesPath = sys_get_temp_dir().'/gf-finish-'.uniqid();
        config(['modules.path' => $this->modulesPath, 'updates.behavior.health_check' => true]);
        $this->makeModule();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->modulesPath);
        parent::tearDown();
    }

    public function test_it_runs_migrations_and_seeders_that_call_other_seeders(): void
    {
        $this->healthCheck(passes: true);

        $this->finish()->assertExitCode(0);

        $this->assertTrue(Schema::hasTable('finish_mod_items'));
        $this->assertSame(1, DB::table('finish_mod_items')->count());
    }

    public function test_it_records_the_new_version_with_the_database_changes(): void
    {
        $this->healthCheck(passes: true);

        $this->finish()->assertExitCode(0);

        $row = ModuleModel::query()->where('name', 'finish-mod')->firstOrFail();
        $this->assertSame('1.0.1-beta', $row->version);
        $this->assertNull($row->latest_available_version);
    }

    public function test_it_reports_the_migrations_applied_and_the_seeders_run(): void
    {
        $this->healthCheck(passes: true);
        Artisan::call('module:finish-update', ['name' => 'finish-mod', 'version' => '1.0.1-beta']);

        $report = PostUpdateReportDTO::fromOutput(Artisan::output());

        $this->assertNotNull($report);
        $this->assertSame(['2026_10_04_000000_create_finish_mod_items_table'], $report->migrations);
        $this->assertSame(['FinishModDatabaseSeeder', 'ItemsSeeder'], $report->seeders);
    }

    public function test_it_does_not_report_migrations_that_had_already_run(): void
    {
        $this->healthCheck(passes: true);
        Artisan::call('module:finish-update', ['name' => 'finish-mod', 'version' => '1.0.1-beta']);
        Artisan::call('module:finish-update', ['name' => 'finish-mod', 'version' => '1.0.1-beta']);

        $report = PostUpdateReportDTO::fromOutput(Artisan::output());

        $this->assertNotNull($report);
        $this->assertSame([], $report->migrations);
    }

    public function test_a_failed_health_check_leaves_the_database_untouched(): void
    {
        $this->healthCheck(passes: false);

        $this->finish()
            ->expectsOutputToContain('provider failed')
            ->assertExitCode(1);

        $this->assertDatabaseUntouched();
    }

    public function test_a_failed_migration_rolls_back_the_migrations_before_it(): void
    {
        $this->healthCheck(passes: true);
        File::put(
            $this->modulesPath.'/finish-mod/database/migrations/2026_10_04_000001_broken.php',
            "<?php\n\nuse Illuminate\\Database\\Migrations\\Migration;\n\nreturn new class extends Migration\n{\n    public function up(): void\n    {\n        throw new RuntimeException('broken migration');\n    }\n};\n",
        );

        $this->finish()
            ->expectsOutputToContain('broken migration')
            ->assertExitCode(1);

        $this->assertDatabaseUntouched();
    }

    public function test_a_failed_seeder_rolls_back_the_migrations(): void
    {
        $this->healthCheck(passes: true);
        File::put($this->modulesPath.'/finish-mod/database/seeders/BrokenSeeder.php', <<<'PHP'
<?php

namespace Modules\FinishMod\Database\Seeders;

use Illuminate\Database\Seeder;

class BrokenSeeder extends Seeder
{
    public function run(): void
    {
        throw new \RuntimeException('broken seeder');
    }
}
PHP);

        $this->finish()
            ->expectsOutputToContain('broken seeder')
            ->assertExitCode(1);

        $this->assertDatabaseUntouched();
    }

    public function test_it_fails_for_an_unknown_module(): void
    {
        $this->artisan('module:finish-update', ['name' => 'missing-mod', 'version' => '1.0.1-beta'])->assertExitCode(1);
    }

    private function finish(): PendingCommand
    {
        $command = $this->artisan('module:finish-update', ['name' => 'finish-mod', 'version' => '1.0.1-beta']);
        assert($command instanceof PendingCommand);

        return $command;
    }

    private function assertDatabaseUntouched(): void
    {
        $this->assertFalse(Schema::hasTable('finish_mod_items'));
        $this->assertFalse(DB::table('migrations')->where('migration', '2026_10_04_000000_create_finish_mod_items_table')->exists());
        $this->assertSame('1.0.0-beta', ModuleModel::query()->where('name', 'finish-mod')->value('version'));
    }

    private function healthCheck(bool $passes): void
    {
        $checker = Mockery::mock(ModuleHealthCheckerInterface::class);
        $checker->shouldReceive('check')->andReturn($passes
            ? new HealthCheckResultDTO(true, true, true)
            : new HealthCheckResultDTO(false, true, true, ['provider failed']));
        $this->app->instance(ModuleHealthCheckerInterface::class, $checker);
    }

    private function makeModule(): void
    {
        $root = $this->modulesPath.'/finish-mod';
        File::ensureDirectoryExists($root.'/database/migrations');
        File::ensureDirectoryExists($root.'/database/seeders');
        File::put($root.'/module.json', (string) json_encode(['name' => 'finish-mod', 'version' => '1.0.1-beta']));

        File::put($root.'/database/migrations/2026_10_04_000000_create_finish_mod_items_table.php', <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finish_mod_items', function (Blueprint $table): void {
            $table->string('name')->primary();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finish_mod_items');
    }
};
PHP);

        // The entry seeder calls a sibling seeder that nothing autoloads (database/ is outside src/)
        File::put($root.'/database/seeders/FinishModDatabaseSeeder.php', <<<'PHP'
<?php

namespace Modules\FinishMod\Database\Seeders;

use Illuminate\Database\Seeder;

class FinishModDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ItemsSeeder::class);
    }
}
PHP);
        File::put($root.'/database/seeders/ItemsSeeder.php', <<<'PHP'
<?php

namespace Modules\FinishMod\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class ItemsSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('finish_mod_items')->updateOrInsert(['name' => 'default']);
    }
}
PHP);

        ModuleModel::factory()->enabled()->create([
            'name' => 'finish-mod',
            'version' => '1.0.0-beta',
            'latest_available_version' => '1.0.1-beta',
            'path' => $root,
            'namespace' => 'Modules\\FinishMod',
            'provider' => 'FinishModServiceProvider',
            'installed_at' => now(),
        ]);
    }
}
