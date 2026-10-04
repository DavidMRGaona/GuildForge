<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands\Updates;

use App\Application\Updates\DTOs\HealthCheckResultDTO;
use App\Application\Updates\Services\ModuleHealthCheckerInterface;
use App\Infrastructure\Persistence\Eloquent\Models\ModuleModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
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

        $this->artisan('module:finish-update', ['name' => 'finish-mod'])->assertExitCode(0);

        $this->assertTrue(Schema::hasTable('finish_mod_items'));
        $this->assertSame(1, DB::table('finish_mod_items')->count());
    }

    public function test_it_fails_when_the_health_check_fails(): void
    {
        $this->healthCheck(passes: false);

        $this->artisan('module:finish-update', ['name' => 'finish-mod'])
            ->expectsOutputToContain('provider failed')
            ->assertExitCode(1);
    }

    public function test_it_fails_when_a_migration_fails(): void
    {
        $this->healthCheck(passes: true);
        File::put(
            $this->modulesPath.'/finish-mod/database/migrations/2026_10_04_000001_broken.php',
            "<?php\n\nuse Illuminate\\Database\\Migrations\\Migration;\n\nreturn new class extends Migration\n{\n    public function up(): void\n    {\n        throw new RuntimeException('broken migration');\n    }\n};\n",
        );

        $this->artisan('module:finish-update', ['name' => 'finish-mod'])
            ->expectsOutputToContain('broken migration')
            ->assertExitCode(1);
    }

    public function test_it_fails_for_an_unknown_module(): void
    {
        $this->artisan('module:finish-update', ['name' => 'missing-mod'])->assertExitCode(1);
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
            'version' => '1.0.1-beta',
            'path' => $root,
            'namespace' => 'Modules\\FinishMod',
            'provider' => 'FinishModServiceProvider',
            'installed_at' => now(),
        ]);
    }
}
