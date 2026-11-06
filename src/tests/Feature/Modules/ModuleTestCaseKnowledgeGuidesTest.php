<?php

declare(strict_types=1);

namespace Tests\Feature\Modules;

use App\Infrastructure\Persistence\Eloquent\Models\ModuleModel;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The admin panel is built while the application boots, before a module test enables its
 * module: the guide lint helper must still find the routes of the module's panel resources
 * and pages, as `knowledge:lint` does on a booted site.
 */
final class ModuleTestCaseKnowledgeGuidesTest extends ModuleTestCase
{
    private string $modulesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modulesPath = sys_get_temp_dir().'/gf-paneled-'.bin2hex(random_bytes(8));
        File::copyDirectory(base_path('tests/Fixtures/modules/paneled-module'), "{$this->modulesPath}/paneled-module");
        config(['modules.path' => $this->modulesPath]);

        ModuleModel::factory()->disabled()->create([
            'name' => 'paneled-module',
            'path' => "{$this->modulesPath}/paneled-module",
            'namespace' => 'Modules\\PaneledModule',
            'provider' => 'PaneledModuleServiceProvider',
            'requires' => ['core' => '>=2.0'],
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->modulesPath);
        parent::tearDown();
    }

    public function test_panel_guides_may_link_to_the_module_resources_and_pages(): void
    {
        $this->assertFalse(Route::has('filament.admin.resources.things.index'));

        $this->assertModuleKnowledgeGuidesAreValid('paneled-module');

        $this->assertTrue(Route::has('filament.admin.resources.things.index'));
        $this->assertTrue(Route::has('filament.admin.pages.thing-settings'));
    }

    public function test_it_also_works_when_the_test_already_enabled_the_module(): void
    {
        $this->discoverModules();
        $this->enableModule('paneled-module');

        $this->assertModuleKnowledgeGuidesAreValid('paneled-module');
    }

    public function test_registering_the_panel_twice_keeps_the_guides_valid(): void
    {
        // Modules written before the helper did it put their resources and pages on the panel
        // themselves, so the helper registers them a second time
        $this->assertModuleKnowledgeGuidesAreValid('paneled-module');

        $this->assertModuleKnowledgeGuidesAreValid('paneled-module');
    }
}
