<?php

declare(strict_types=1);

namespace Tests\Unit\View\Components;

use App\View\Components\ModuleImportMap;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class ModuleImportMapTest extends TestCase
{
    private string $manifestPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manifestPath = public_path('build/manifest.json');
    }

    protected function tearDown(): void
    {
        // Clean up any test manifest we created
        if (File::exists($this->manifestPath) && File::exists(dirname($this->manifestPath).'/.test-manifest')) {
            File::delete($this->manifestPath);
            File::delete(dirname($this->manifestPath).'/.test-manifest');
        }

        parent::tearDown();
    }

    public function test_returns_empty_imports_when_manifest_does_not_exist(): void
    {
        // Ensure manifest doesn't exist for this test
        $originalExists = File::exists($this->manifestPath);
        if ($originalExists) {
            $this->markTestSkipped('Cannot test missing manifest when production manifest exists');
        }

        $component = new ModuleImportMap();
        $view = $component->render();
        $html = $view->render();

        $this->assertEmpty($html);
    }

    public function test_returns_import_map_with_vue_export(): void
    {
        $this->createTestManifest([
            'resources/js/vendor-exports/vue.ts' => [
                'file' => 'assets/vue-ABC123.js',
            ],
        ]);

        $component = new ModuleImportMap();
        $view = $component->render();
        $html = $view->render();

        $this->assertStringContainsString('<script type="importmap">', $html);
        $this->assertStringContainsString('"vue": "/build/assets/vue-ABC123.js"', $html);
    }

    public function test_returns_import_map_with_pinia_export(): void
    {
        $this->createTestManifest([
            'resources/js/vendor-exports/pinia.ts' => [
                'file' => 'assets/pinia-DEF456.js',
            ],
        ]);

        $component = new ModuleImportMap();
        $view = $component->render();
        $html = $view->render();

        $this->assertStringContainsString('<script type="importmap">', $html);
        $this->assertStringContainsString('"pinia": "/build/assets/pinia-DEF456.js"', $html);
    }

    public function test_returns_import_map_with_inertia_export(): void
    {
        $this->createTestManifest([
            'resources/js/vendor-exports/inertia.ts' => [
                'file' => 'assets/inertia-GHI789.js',
            ],
        ]);

        $component = new ModuleImportMap();
        $view = $component->render();
        $html = $view->render();

        $this->assertStringContainsString('<script type="importmap">', $html);
        $this->assertStringContainsString('"@inertiajs/vue3": "/build/assets/inertia-GHI789.js"', $html);
    }

    public function test_returns_complete_import_map_with_all_exports(): void
    {
        $this->createTestManifest([
            'resources/js/vendor-exports/vue.ts' => [
                'file' => 'assets/vue-ABC123.js',
            ],
            'resources/js/vendor-exports/pinia.ts' => [
                'file' => 'assets/pinia-DEF456.js',
            ],
            'resources/js/vendor-exports/vue-i18n.ts' => [
                'file' => 'assets/vue-i18n-GHI789.js',
            ],
            'resources/js/vendor-exports/inertia.ts' => [
                'file' => 'assets/inertia-JKL012.js',
            ],
            'resources/js/app.ts' => [
                'file' => 'assets/app-XYZ789.js',
                'isEntry' => true,
            ],
        ]);

        $component = new ModuleImportMap();
        $view = $component->render();
        $html = $view->render();

        // Verify all expected imports are present
        $this->assertStringContainsString('"vue": "/build/assets/vue-ABC123.js"', $html);
        $this->assertStringContainsString('"pinia": "/build/assets/pinia-DEF456.js"', $html);
        $this->assertStringContainsString('"vue-i18n": "/build/assets/vue-i18n-GHI789.js"', $html);
        $this->assertStringContainsString('"@inertiajs/vue3": "/build/assets/inertia-JKL012.js"', $html);
    }

    public function test_returns_empty_when_no_vendor_exports_in_manifest(): void
    {
        $this->createTestManifest([
            'resources/js/app.ts' => [
                'file' => 'assets/app-XYZ789.js',
                'isEntry' => true,
            ],
        ]);

        $component = new ModuleImportMap();
        $view = $component->render();
        $html = $view->render();

        // Should be empty because no vendor exports were found
        $this->assertEmpty($html);
    }

    public function test_handles_malformed_json_gracefully(): void
    {
        // Create directory if it doesn't exist
        File::ensureDirectoryExists(dirname($this->manifestPath));

        // Write invalid JSON
        File::put($this->manifestPath, 'not valid json {{{');
        File::put(dirname($this->manifestPath).'/.test-manifest', '1');

        $component = new ModuleImportMap();
        $view = $component->render();
        $html = $view->render();

        $this->assertEmpty($html);
    }

    /**
     * Create a test manifest file with the given entries.
     *
     * @param  array<string, array<string, mixed>>  $entries
     */
    private function createTestManifest(array $entries): void
    {
        File::ensureDirectoryExists(dirname($this->manifestPath));

        $json = json_encode($entries, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        File::put($this->manifestPath, $json);

        // Mark that we created a test manifest so tearDown knows to clean it up
        File::put(dirname($this->manifestPath).'/.test-manifest', '1');
    }
}