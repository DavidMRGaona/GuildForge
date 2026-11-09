<?php

declare(strict_types=1);

namespace Tests\Feature\Modules;

use App\Application\Modules\Services\EnabledModulesResolverInterface;
use App\Infrastructure\Persistence\Eloquent\Models\ModuleModel;
use App\Modules\ModuleLoader;
use App\Providers\ModulesServiceProvider;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\File;
use Modules\HookTranslationsModule\HookTranslationsModuleServiceProvider;
use Tests\TestCase;

/**
 * Modules translate labels inside their registration hooks (permissions, navigation) before
 * their own boot() has loaded their translations. ModuleLoader registers the module's
 * translation namespace first, or the translator caches the group as empty and the panel
 * shows raw keys such as `tournaments::messages.resource.navigation` (core#429).
 */
final class ModuleTranslationsInHooksTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $modulesPath = storage_path('framework/testing/hook-translations-'.bin2hex(random_bytes(8)).'/modules');
        $this->root = dirname($modulesPath);
        File::copyDirectory(base_path('tests/Fixtures/modules/hook-translations-module'), "{$modulesPath}/hook-translations-module");
        config(['modules.path' => $modulesPath]);

        // The fixture class only autoloads once ModuleLoader has registered the module
        if (class_exists(HookTranslationsModuleServiceProvider::class, false)) {
            HookTranslationsModuleServiceProvider::$permissionLabel = null;
        }

        ModuleModel::factory()->enabled()->create([
            'name' => 'hook-translations-module',
            'path' => "{$modulesPath}/hook-translations-module",
            'namespace' => 'Modules\\HookTranslationsModule',
            'provider' => 'HookTranslationsModuleServiceProvider',
        ]);

        app(EnabledModulesResolverInterface::class)->reset();
        $this->app->forgetInstance(ModuleLoader::class);
        (new ModulesServiceProvider($this->app))->boot();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    public function test_a_label_translated_in_a_registration_hook_is_translated(): void
    {
        $this->assertContains('hook-translations-module', app(ModuleLoader::class)->loadedModules());
        $this->assertSame('Ver cosas', HookTranslationsModuleServiceProvider::$permissionLabel);
    }

    public function test_the_rest_of_the_group_stays_translated_afterwards(): void
    {
        $this->assertSame('Cosas', __('hook-translations-module::messages.navigation'));
    }
}
