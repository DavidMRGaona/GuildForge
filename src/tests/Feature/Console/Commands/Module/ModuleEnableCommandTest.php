<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands\Module;

use App\Application\Updates\Services\CoreVersionServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\ModuleModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class ModuleEnableCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $modulesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modulesPath = sys_get_temp_dir().'/gf-enable-cmd-'.uniqid();
        config(['modules.path' => $this->modulesPath]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->modulesPath);
        parent::tearDown();
    }

    public function test_it_enables_a_disabled_module(): void
    {
        $this->moduleOnDisk('test-module');
        $module = ModuleModel::factory()->disabled()->create([
            'name' => 'test-module',
        ]);

        $this->artisan('module:enable', ['module' => 'test-module'])
            ->expectsOutput('Module "test-module" has been enabled.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('modules', [
            'id' => $module->id,
            'name' => 'test-module',
            'status' => 'enabled',
        ]);

        $module->refresh();
        $this->assertNotNull($module->enabled_at);
    }

    public function test_it_fails_when_module_not_found(): void
    {
        $this->artisan('module:enable', ['module' => 'non-existent-module'])
            ->expectsOutput('Module "non-existent-module" not found.')
            ->assertExitCode(1);
    }

    public function test_it_fails_when_module_already_enabled(): void
    {
        ModuleModel::factory()->enabled()->create([
            'name' => 'test-module',
        ]);

        $this->artisan('module:enable', ['module' => 'test-module'])
            ->expectsOutput('Module "test-module" is already enabled.')
            ->assertExitCode(1);
    }

    public function test_it_fails_when_dependencies_not_satisfied(): void
    {
        // Create a module with dependencies
        ModuleModel::factory()->disabled()->create([
            'name' => 'dependent-module',
            'dependencies' => ['required-module'],
        ]);

        $this->artisan('module:enable', ['module' => 'dependent-module'])
            ->expectsOutput('Cannot enable module "dependent-module". Missing dependencies: required-module')
            ->assertExitCode(1);

        $this->assertDatabaseHas('modules', [
            'name' => 'dependent-module',
            'status' => 'disabled',
        ]);
    }

    public function test_it_refuses_an_incompatible_module(): void
    {
        $this->moduleOnDisk('test-module', ['core' => '^99.0']);
        ModuleModel::factory()->disabled()->create(['name' => 'test-module', 'version' => '1.0.0']);

        $core = $this->app->make(CoreVersionServiceInterface::class)->getCurrentVersion()->value();

        $this->artisan('module:enable', ['module' => 'test-module'])
            ->expectsOutput("No se puede habilitar test-module: requiere core ^99.0, instalado {$core}")
            ->assertExitCode(1);

        $this->assertDatabaseHas('modules', ['name' => 'test-module', 'status' => 'disabled']);
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
    }
}
