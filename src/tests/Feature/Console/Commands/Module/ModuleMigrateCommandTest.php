<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands\Module;

use App\Application\Updates\Services\CoreVersionServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\ModuleModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class ModuleMigrateCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $modulesPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Never the real modules directory: other test classes running in parallel resolve
        // modules without a stored path to base_path('modules/<name>'). Kept under base_path()
        // so the module migrations are passed to the migrator as a relative path
        $this->modulesPath = base_path('storage/framework/testing/module-migrate-'.bin2hex(random_bytes(8)).'/modules');
        config(['modules.path' => $this->modulesPath]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(dirname($this->modulesPath));

        parent::tearDown();
    }

    public function test_it_runs_migrations_for_specific_module(): void
    {
        ModuleModel::factory()->enabled()->create([
            'name' => 'test-module',
            'path' => $this->modulesPath.'/test-module',
        ]);

        // Create module directory with migrations folder
        $modulePath = $this->modulesPath.'/test-module';
        $migrationsPath = $modulePath.'/database/migrations';
        File::makeDirectory($migrationsPath, 0755, true);
        File::put($modulePath.'/module.json', json_encode([
            'name' => 'test-module', 'version' => '1.0.0', 'storesUtc' => true, 'namespace' => 'Modules\\TestModule', 'provider' => 'TestModuleServiceProvider',
            'requires' => ['core' => '>=2.0'],
        ], JSON_THROW_ON_ERROR));

        // Create a simple test migration file
        $migrationFile = $migrationsPath.'/2024_01_01_000000_create_test_table.php';
        File::put(
            $migrationFile,
            <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('testmodule_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testmodule_items');
    }
};
PHP
        );

        $this->artisanCommand('module:migrate', ['module' => 'test-module'])
            ->expectsOutput('Running migrations for module: test-module')
            ->assertExitCode(0);

        // Verify migration was executed
        $this->assertTrue(Schema::hasTable('testmodule_items'));
    }

    public function test_it_fails_when_module_not_found(): void
    {
        $this->artisanCommand('module:migrate', ['module' => 'non-existent-module'])
            ->expectsOutput('Module "non-existent-module" not found.')
            ->assertExitCode(1);
    }

    public function test_it_refuses_to_migrate_an_incompatible_module(): void
    {
        ModuleModel::factory()->enabled()->create(['name' => 'test-module', 'version' => '1.0.0', 'path' => $this->modulesPath.'/test-module']);
        File::makeDirectory($this->modulesPath.'/test-module/database/migrations', 0755, true);
        File::put($this->modulesPath.'/test-module/module.json', json_encode([
            'name' => 'test-module', 'version' => '1.0.0', 'storesUtc' => true, 'namespace' => 'Modules\\TestModule', 'provider' => 'TestModuleServiceProvider',
            'requires' => ['core' => '^99.0'],
        ], JSON_THROW_ON_ERROR));

        $core = $this->app->make(CoreVersionServiceInterface::class)->getCurrentVersion()->value();

        $this->artisanCommand('module:migrate', ['module' => 'test-module'])
            ->expectsOutput("No se puede habilitar test-module: requiere core ^99.0, instalado {$core}")
            ->assertExitCode(1);
    }
}
