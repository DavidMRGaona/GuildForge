<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands\Module;

use App\Application\Updates\Services\CoreVersionServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\ModuleModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class ModuleSeedCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $modulesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modulesPath = sys_get_temp_dir().'/gf-module-seed-'.uniqid();
        config(['modules.path' => $this->modulesPath]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->modulesPath);
        parent::tearDown();
    }

    public function test_it_reports_a_compatible_module_without_seeders(): void
    {
        $this->moduleOnDisk('seed-gate-module');

        $this->artisan('module:seed', ['module' => 'seed-gate-module'])
            ->expectsOutput('No seeders to run.')
            ->assertExitCode(0);
    }

    public function test_it_refuses_to_seed_an_incompatible_module(): void
    {
        $this->moduleOnDisk('seed-gate-module', ['core' => '^99.0']);
        File::ensureDirectoryExists("{$this->modulesPath}/seed-gate-module/database/seeders");
        File::put("{$this->modulesPath}/seed-gate-module/database/seeders/SeedGateModuleSeeder.php", <<<'PHP'
<?php

namespace Modules\SeedGateModule\Database\Seeders;

use Illuminate\Database\Seeder;

class SeedGateModuleSeeder extends Seeder
{
    public function run(): void
    {
    }
}
PHP);

        $core = $this->app->make(CoreVersionServiceInterface::class)->getCurrentVersion()->value();

        $this->artisan('module:seed', ['module' => 'seed-gate-module'])
            ->expectsOutput("No se pueden ejecutar los seeders de seed-gate-module en este servidor: requiere core ^99.0, instalado {$core}")
            ->doesntExpectOutputToContain('Running seeders')
            ->assertExitCode(1);

        // Seeding would have compiled the module's code
        $this->assertFalse(class_exists('Modules\\SeedGateModule\\Database\\Seeders\\SeedGateModuleSeeder', false));
    }

    public function test_it_reports_an_unknown_module(): void
    {
        $this->artisan('module:seed', ['module' => 'missing-module'])
            ->expectsOutput('Module "missing-module" not found.')
            ->assertExitCode(1);
    }

    /**
     * @param  array<string, mixed>  $requires
     */
    private function moduleOnDisk(string $name, array $requires = []): void
    {
        File::ensureDirectoryExists("{$this->modulesPath}/{$name}");
        File::put("{$this->modulesPath}/{$name}/module.json", (string) json_encode([
            'name' => $name,
            'version' => '1.0.0',
            'namespace' => 'Modules\\'.str_replace('-', '', ucwords($name, '-')),
            'provider' => str_replace('-', '', ucwords($name, '-')).'ServiceProvider',
            'requires' => $requires + ['core' => '>=2.0'],
        ]));
        ModuleModel::factory()->enabled()->create([
            'name' => $name,
            'version' => '1.0.0',
            'path' => "{$this->modulesPath}/{$name}",
            'namespace' => 'Modules\\'.str_replace('-', '', ucwords($name, '-')),
        ]);
    }
}
