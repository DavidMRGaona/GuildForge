<?php

declare(strict_types=1);

namespace Tests\Feature\Modules;

use App\Application\Modules\DTOs\RejectedModuleDTO;
use App\Application\Modules\Services\EnabledModulesResolverInterface;
use App\Infrastructure\Persistence\Eloquent\Models\ModuleModel;
use App\Modules\ModuleLoader;
use App\Providers\ModulesServiceProvider;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * A site moved to another folder, a backup restored elsewhere or a container with another
 * mount point leaves the stored module path stale: the module must still load from the
 * modules directory, and a module found nowhere must show up as rejected.
 */
final class StaleModulePathBootTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $root;

    private string $modulesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modulesPath = storage_path('framework/testing/stale-path-'.bin2hex(random_bytes(8)).'/modules');
        $this->root = dirname($this->modulesPath);
        File::copyDirectory(base_path('tests/Fixtures/modules/test-module'), "{$this->modulesPath}/test-module");

        config(['modules.path' => $this->modulesPath]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    public function test_a_module_whose_stored_path_moved_is_loaded_from_the_modules_directory(): void
    {
        $this->enable('test-module', 'TestModule');

        $this->boot();

        $this->assertContains('test-module', app(ModuleLoader::class)->loadedModules());
    }

    public function test_a_module_found_nowhere_is_rejected_and_not_loaded(): void
    {
        $this->enable('missing-module', 'MissingModule');

        $this->boot();

        $this->assertNotContains('missing-module', app(ModuleLoader::class)->loadedModules());
        $this->assertSame(['missing-module'], array_map(
            static fn (RejectedModuleDTO $rejected): string => $rejected->name,
            app(EnabledModulesResolverInterface::class)->rejected(),
        ));
    }

    private function enable(string $name, string $studly): void
    {
        ModuleModel::factory()->enabled()->create([
            'name' => $name,
            'path' => "/old/site/modules/{$name}",
            'namespace' => "Modules\\{$studly}",
            'provider' => "{$studly}ServiceProvider",
        ]);
    }

    private function boot(): void
    {
        app(EnabledModulesResolverInterface::class)->reset();
        $this->app->forgetInstance(ModuleLoader::class);

        (new ModulesServiceProvider($this->app))->boot();
    }
}
