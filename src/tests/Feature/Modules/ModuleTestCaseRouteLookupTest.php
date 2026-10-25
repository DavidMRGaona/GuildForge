<?php

declare(strict_types=1);

namespace Tests\Feature\Modules;

use App\Infrastructure\Persistence\Eloquent\Models\ModuleModel;
use Illuminate\Support\Facades\File;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Module tests enable their module after the application booted: the routes it registers
 * then must be found by name and by action, as they are on a booted site.
 */
final class ModuleTestCaseRouteLookupTest extends ModuleTestCase
{
    // Autoloaded from the fixture module only once the module is enabled
    private const string PING_CONTROLLER = 'Modules\\RoutedModule\\Http\\PingController';

    private string $modulesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modulesPath = sys_get_temp_dir().'/gf-routed-'.bin2hex(random_bytes(8));
        File::copyDirectory(base_path('tests/Fixtures/modules/routed-module'), "{$this->modulesPath}/routed-module");
        config(['modules.path' => $this->modulesPath]);

        ModuleModel::factory()->disabled()->create([
            'name' => 'routed-module',
            'path' => "{$this->modulesPath}/routed-module",
            'namespace' => 'Modules\\RoutedModule',
            'provider' => 'RoutedModuleServiceProvider',
            'requires' => ['core' => '>=2.0'],
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->modulesPath);
        parent::tearDown();
    }

    public function test_a_module_route_name_resolves_after_enabling_the_module(): void
    {
        $this->enableModule('routed-module');

        $this->assertSame(url('/routed-module/ping'), route('routed-module.ping'));
        $this->get(route('routed-module.ping'))->assertOk()->assertSee('pong');
    }

    public function test_a_module_controller_action_resolves_after_enabling_the_module(): void
    {
        $this->enableModule('routed-module');

        $this->assertSame(url('/routed-module/action'), action([self::PING_CONTROLLER, 'show']));
    }
}
