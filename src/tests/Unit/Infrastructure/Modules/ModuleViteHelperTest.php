<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Modules;

use App\Infrastructure\Modules\Services\ModuleViteHelper;
use App\Modules\ModuleLoader;
use Illuminate\Support\Facades\File;
use ReflectionMethod;
use Tests\TestCase;

final class ModuleViteHelperTest extends TestCase
{
    private string $manifestDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manifestDirectory = public_path('build/modules/vite-helper-fixture');
        File::ensureDirectoryExists($this->manifestDirectory);
        File::put($this->manifestDirectory.'/manifest.json', json_encode([
            'modules/vite-helper-fixture/resources/js/app.ts' => [
                'file' => 'assets/app-hash.js',
                'css' => ['assets/app-hash.css'],
            ],
        ], JSON_THROW_ON_ERROR));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->manifestDirectory);

        parent::tearDown();
    }

    /**
     * A module stylesheet has to be imported into the module cascade layer.
     * A plain <link> cannot carry one, and unlayered rules outrank every layered
     * rule in the core stylesheet, which is enough for a module's own `.hidden`
     * to hide the site header at any viewport width.
     */
    public function test_module_stylesheets_are_imported_into_the_module_layer(): void
    {
        $tags = $this->makeProductionTags('vite-helper-fixture');

        self::assertStringContainsString(
            '@import url("/build/modules/vite-helper-fixture/assets/app-hash.css") layer(module-utilities);',
            $tags,
        );
        self::assertStringNotContainsString('<link rel="stylesheet"', $tags);
    }

    public function test_module_scripts_are_still_emitted(): void
    {
        self::assertStringContainsString(
            '<script type="module" src="/build/modules/vite-helper-fixture/assets/app-hash.js"></script>',
            $this->makeProductionTags('vite-helper-fixture'),
        );
    }

    private function makeProductionTags(string $moduleName): string
    {
        $helper = new ModuleViteHelper(app(ModuleLoader::class));

        $method = new ReflectionMethod($helper, 'makeProductionTags');

        return (string) $method->invoke(
            $helper,
            $moduleName,
            ["modules/{$moduleName}/resources/js/app.ts"],
        );
    }
}
