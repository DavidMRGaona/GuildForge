<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands\Module;

use App\Infrastructure\Persistence\Eloquent\Models\ModuleModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class ModuleMigrateCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $modulesPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->modulesPath = base_path('modules');
    }

    protected function tearDown(): void
    {
        // Clean up test module
        if (File::exists($this->modulesPath . '/test-module')) {
            File::deleteDirectory($this->modulesPath . '/test-module');
        }

        parent::tearDown();
    }

    public function test_it_runs_migrations_for_specific_module(): void
    {
        $module = ModuleModel::factory()->enabled()->create([
            'name' => 'test-module',
            'path' => $this->modulesPath . '/test-module',
        ]);

        // Create module directory with migrations folder
        $modulePath = $this->modulesPath . '/test-module';
        $migrationsPath = $modulePath . '/database/migrations';
        File::makeDirectory($migrationsPath, 0755, true);

        // Create a simple test migration file
        $migrationFile = $migrationsPath . '/2024_01_01_000000_create_test_table.php';
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
        Schema::create('module_test_table', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_test_table');
    }
};
PHP
        );

        $this->artisan('module:migrate', ['module' => 'test-module'])
            ->expectsOutput('Running migrations for module: test-module')
            ->assertExitCode(0);

        // Verify migration was executed
        $this->assertTrue(Schema::hasTable('module_test_table'));
    }

    public function test_it_fails_when_module_not_found(): void
    {
        $this->artisan('module:migrate', ['module' => 'non-existent-module'])
            ->expectsOutput('Module "non-existent-module" not found.')
            ->assertExitCode(1);
    }
}
