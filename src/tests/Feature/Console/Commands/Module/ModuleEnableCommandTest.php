<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands\Module;

use App\Application\Updates\Services\CoreVersionServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\ModuleModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

final class ModuleEnableCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $modulesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modulesPath = sys_get_temp_dir().'/gf-enable-cmd-'.bin2hex(random_bytes(8));
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

        $this->artisanCommand('module:enable', ['module' => 'test-module'])
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

    public function test_it_says_how_to_build_the_assets_when_node_is_missing(): void
    {
        Process::fake(['*npx*--version*' => Process::result(exitCode: 127)]);
        $this->moduleOnDisk('test-module');
        $this->withVueComponents('test-module');
        ModuleModel::factory()->disabled()->create([
            'name' => 'test-module',
            'path' => "{$this->modulesPath}/test-module",
        ]);

        $this->artisanCommand('module:enable', ['module' => 'test-module'])
            ->expectsOutputToContain('docker exec guildforge_node npm run build:module -- test-module')
            ->assertExitCode(0);
    }

    public function test_it_says_nothing_about_assets_when_node_can_build_them(): void
    {
        Process::fake();
        $this->moduleOnDisk('test-module');
        $this->withVueComponents('test-module');
        ModuleModel::factory()->disabled()->create([
            'name' => 'test-module',
            'path' => "{$this->modulesPath}/test-module",
        ]);

        $this->artisanCommand('module:enable', ['module' => 'test-module'])
            ->doesntExpectOutputToContain('build:module')
            ->assertExitCode(0);
    }

    public function test_it_fails_when_module_not_found(): void
    {
        $this->artisanCommand('module:enable', ['module' => 'non-existent-module'])
            ->expectsOutput('Module "non-existent-module" not found.')
            ->assertExitCode(1);
    }

    public function test_it_fails_when_module_already_enabled(): void
    {
        ModuleModel::factory()->enabled()->create([
            'name' => 'test-module',
        ]);

        $this->artisanCommand('module:enable', ['module' => 'test-module'])
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

        $this->artisanCommand('module:enable', ['module' => 'dependent-module'])
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

        $this->artisanCommand('module:enable', ['module' => 'test-module'])
            ->expectsOutput("No se puede habilitar test-module: requiere core ^99.0, instalado {$core}")
            ->assertExitCode(1);

        $this->assertDatabaseHas('modules', ['name' => 'test-module', 'status' => 'disabled']);
    }

    private function withVueComponents(string $name): void
    {
        File::ensureDirectoryExists("{$this->modulesPath}/{$name}/resources/js/pages");
        File::put("{$this->modulesPath}/{$name}/resources/js/pages/Index.vue", '<template><div /></template>');
        File::put(
            "{$this->modulesPath}/{$name}/vite.config.ts",
            "import { defineModuleConfig } from '../../vite.module.config.ts';\n\nexport default defineModuleConfig({ root: import.meta.dirname });\n",
        );
    }

    /**
     * @param  array<string, mixed>  $requires
     */
    private function moduleOnDisk(string $name, array $requires = []): void
    {
        File::ensureDirectoryExists("{$this->modulesPath}/{$name}");
        File::put("{$this->modulesPath}/{$name}/module.json", json_encode([
            'name' => $name,
            'version' => '1.0.0',
            'storesUtc' => true,
            'namespace' => 'Modules\\'.str_replace('-', '', ucwords($name, '-')),
            'provider' => str_replace('-', '', ucwords($name, '-')).'ServiceProvider',
            'requires' => $requires + ['core' => '>=2.0'],
        ], JSON_THROW_ON_ERROR));
    }
}
