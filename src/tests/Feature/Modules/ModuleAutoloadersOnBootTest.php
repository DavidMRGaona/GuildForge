<?php

declare(strict_types=1);

namespace Tests\Feature\Modules;

use App\Application\Modules\Services\EnabledModulesResolverInterface;
use App\Infrastructure\Persistence\Eloquent\Models\ModuleModel;
use App\Modules\ModuleLoader;
use App\Providers\ModulesServiceProvider;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Every test (and every queue job in a long-lived worker) boots a new application, while
 * the autoloader stack lives as long as the process: booting the modules again must not
 * add autoloaders, or each one keeps its application alive (GuildForge#548).
 */
final class ModuleAutoloadersOnBootTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $root;

    private string $modulesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modulesPath = storage_path('framework/testing/autoloaders-'.bin2hex(random_bytes(8)).'/modules');
        $this->root = dirname($this->modulesPath);
        File::copyDirectory(base_path('tests/Fixtures/modules/test-module'), "{$this->modulesPath}/test-module");

        config(['modules.path' => $this->modulesPath]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    public function test_booting_the_modules_again_adds_no_autoloader(): void
    {
        ModuleModel::factory()->enabled()->create([
            'name' => 'test-module',
            'path' => "{$this->modulesPath}/test-module",
            'namespace' => 'Modules\\TestModule',
            'provider' => 'TestModuleServiceProvider',
        ]);

        $this->bootModules();
        $this->assertContains('test-module', app(ModuleLoader::class)->loadedModules());
        $after = count(spl_autoload_functions());

        $this->bootModules();

        $this->assertContains('test-module', app(ModuleLoader::class)->loadedModules());
        $this->assertCount($after, spl_autoload_functions());
    }

    private function bootModules(): void
    {
        app(EnabledModulesResolverInterface::class)->reset();
        $this->app->forgetInstance(ModuleLoader::class);

        (new ModulesServiceProvider($this->app))->boot();
    }
}
