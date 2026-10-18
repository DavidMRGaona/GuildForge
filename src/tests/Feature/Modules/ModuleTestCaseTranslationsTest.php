<?php

declare(strict_types=1);

namespace Tests\Feature\Modules;

use App\Infrastructure\Persistence\Eloquent\Models\ModuleModel;
use Illuminate\Support\Facades\File;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The first test of a process runs migrate:fresh, whose module migrations may translate a
 * module key before the module boots; the translator then caches that group as empty. Once
 * a test enables the module, its texts must resolve anyway.
 */
final class ModuleTestCaseTranslationsTest extends ModuleTestCase
{
    private const string KEY = 'translated-module::messages.greeting';

    private string $modulesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modulesPath = sys_get_temp_dir().'/gf-translated-'.bin2hex(random_bytes(8));
        File::copyDirectory(base_path('tests/Fixtures/modules/translated-module'), "{$this->modulesPath}/translated-module");
        config(['modules.path' => $this->modulesPath]);

        ModuleModel::factory()->disabled()->create([
            'name' => 'translated-module',
            'path' => "{$this->modulesPath}/translated-module",
            'namespace' => 'Modules\\TranslatedModule',
            'provider' => 'TranslatedModuleServiceProvider',
            'requires' => ['core' => '>=2.0'],
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->modulesPath);
        parent::tearDown();
    }

    public function test_a_module_key_translated_before_the_module_boots_resolves_once_it_is_enabled(): void
    {
        // What a module migration does during migrate:fresh: the namespace is unknown yet
        $this->assertSame(self::KEY, __(self::KEY));

        $this->enableModule('translated-module');

        $this->assertSame('Hola desde el módulo de pruebas', __(self::KEY));
    }

    public function test_core_translations_loaded_before_enabling_a_module_still_resolve(): void
    {
        $this->assertSame('El campo nombre es obligatorio.', __('validation.required', ['attribute' => 'nombre']));

        $this->enableModule('translated-module');

        $this->assertSame('El campo nombre es obligatorio.', __('validation.required', ['attribute' => 'nombre']));
        $this->assertSame('Hola desde el módulo de pruebas', __(self::KEY));
    }
}
