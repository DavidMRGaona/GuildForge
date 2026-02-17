<?php

declare(strict_types=1);

namespace Tests\Unit\View\Components;

use App\View\Components\ModuleImportMap;
use Illuminate\Support\Facades\Vite;
use Tests\TestCase;

final class ModuleImportMapTest extends TestCase
{
    public function test_returns_empty_imports_when_no_vendor_exports_found(): void
    {
        Vite::shouldReceive('asset')
            ->andThrow(new \Exception('Manifest not found'));

        $component = new ModuleImportMap();
        $view = $component->render();
        $html = $view->render();

        $this->assertEmpty($html);
    }

    public function test_returns_import_map_with_vue_export(): void
    {
        Vite::shouldReceive('asset')
            ->with('resources/js/vendor-exports/vue.ts')
            ->andReturn('/build/assets/vue-ABC123.js');

        Vite::shouldReceive('asset')
            ->with('resources/js/vendor-exports/pinia.ts')
            ->andThrow(new \Exception('Not found'));

        Vite::shouldReceive('asset')
            ->with('resources/js/vendor-exports/vue-i18n.ts')
            ->andThrow(new \Exception('Not found'));

        Vite::shouldReceive('asset')
            ->with('resources/js/vendor-exports/inertia.ts')
            ->andThrow(new \Exception('Not found'));

        Vite::shouldReceive('asset')
            ->with('resources/js/vendor-exports/unhead.ts')
            ->andThrow(new \Exception('Not found'));

        $component = new ModuleImportMap();
        $view = $component->render();
        $html = $view->render();

        $this->assertStringContainsString('<script type="importmap">', $html);
        $this->assertStringContainsString('"vue": "/build/assets/vue-ABC123.js"', $html);
    }

    public function test_returns_import_map_with_pinia_export(): void
    {
        Vite::shouldReceive('asset')
            ->with('resources/js/vendor-exports/vue.ts')
            ->andThrow(new \Exception('Not found'));

        Vite::shouldReceive('asset')
            ->with('resources/js/vendor-exports/pinia.ts')
            ->andReturn('/build/assets/pinia-DEF456.js');

        Vite::shouldReceive('asset')
            ->with('resources/js/vendor-exports/vue-i18n.ts')
            ->andThrow(new \Exception('Not found'));

        Vite::shouldReceive('asset')
            ->with('resources/js/vendor-exports/inertia.ts')
            ->andThrow(new \Exception('Not found'));

        Vite::shouldReceive('asset')
            ->with('resources/js/vendor-exports/unhead.ts')
            ->andThrow(new \Exception('Not found'));

        $component = new ModuleImportMap();
        $view = $component->render();
        $html = $view->render();

        $this->assertStringContainsString('<script type="importmap">', $html);
        $this->assertStringContainsString('"pinia": "/build/assets/pinia-DEF456.js"', $html);
    }

    public function test_returns_import_map_with_inertia_export(): void
    {
        Vite::shouldReceive('asset')
            ->with('resources/js/vendor-exports/vue.ts')
            ->andThrow(new \Exception('Not found'));

        Vite::shouldReceive('asset')
            ->with('resources/js/vendor-exports/pinia.ts')
            ->andThrow(new \Exception('Not found'));

        Vite::shouldReceive('asset')
            ->with('resources/js/vendor-exports/vue-i18n.ts')
            ->andThrow(new \Exception('Not found'));

        Vite::shouldReceive('asset')
            ->with('resources/js/vendor-exports/inertia.ts')
            ->andReturn('/build/assets/inertia-GHI789.js');

        Vite::shouldReceive('asset')
            ->with('resources/js/vendor-exports/unhead.ts')
            ->andThrow(new \Exception('Not found'));

        $component = new ModuleImportMap();
        $view = $component->render();
        $html = $view->render();

        $this->assertStringContainsString('<script type="importmap">', $html);
        $this->assertStringContainsString('"@inertiajs/vue3": "/build/assets/inertia-GHI789.js"', $html);
    }

    public function test_returns_import_map_with_unhead_export(): void
    {
        Vite::shouldReceive('asset')
            ->with('resources/js/vendor-exports/vue.ts')
            ->andThrow(new \Exception('Not found'));

        Vite::shouldReceive('asset')
            ->with('resources/js/vendor-exports/pinia.ts')
            ->andThrow(new \Exception('Not found'));

        Vite::shouldReceive('asset')
            ->with('resources/js/vendor-exports/vue-i18n.ts')
            ->andThrow(new \Exception('Not found'));

        Vite::shouldReceive('asset')
            ->with('resources/js/vendor-exports/inertia.ts')
            ->andThrow(new \Exception('Not found'));

        Vite::shouldReceive('asset')
            ->with('resources/js/vendor-exports/unhead.ts')
            ->andReturn('/build/assets/unhead-MNO345.js');

        $component = new ModuleImportMap();
        $view = $component->render();
        $html = $view->render();

        $this->assertStringContainsString('<script type="importmap">', $html);
        $this->assertStringContainsString('"@unhead/vue": "/build/assets/unhead-MNO345.js"', $html);
    }

    public function test_returns_complete_import_map_with_all_exports(): void
    {
        Vite::shouldReceive('asset')
            ->with('resources/js/vendor-exports/vue.ts')
            ->andReturn('/build/assets/vue-ABC123.js');

        Vite::shouldReceive('asset')
            ->with('resources/js/vendor-exports/pinia.ts')
            ->andReturn('/build/assets/pinia-DEF456.js');

        Vite::shouldReceive('asset')
            ->with('resources/js/vendor-exports/vue-i18n.ts')
            ->andReturn('/build/assets/vue-i18n-GHI789.js');

        Vite::shouldReceive('asset')
            ->with('resources/js/vendor-exports/inertia.ts')
            ->andReturn('/build/assets/inertia-JKL012.js');

        Vite::shouldReceive('asset')
            ->with('resources/js/vendor-exports/unhead.ts')
            ->andReturn('/build/assets/unhead-MNO345.js');

        $component = new ModuleImportMap();
        $view = $component->render();
        $html = $view->render();

        $this->assertStringContainsString('"vue": "/build/assets/vue-ABC123.js"', $html);
        $this->assertStringContainsString('"pinia": "/build/assets/pinia-DEF456.js"', $html);
        $this->assertStringContainsString('"vue-i18n": "/build/assets/vue-i18n-GHI789.js"', $html);
        $this->assertStringContainsString('"@inertiajs/vue3": "/build/assets/inertia-JKL012.js"', $html);
        $this->assertStringContainsString('"@unhead/vue": "/build/assets/unhead-MNO345.js"', $html);
    }

    public function test_handles_errors_gracefully(): void
    {
        Vite::shouldReceive('asset')
            ->with('resources/js/vendor-exports/vue.ts')
            ->andReturn('/build/assets/vue-ABC123.js');

        Vite::shouldReceive('asset')
            ->with('resources/js/vendor-exports/pinia.ts')
            ->andThrow(new \RuntimeException('Manifest error'));

        Vite::shouldReceive('asset')
            ->with('resources/js/vendor-exports/vue-i18n.ts')
            ->andThrow(new \Error('Fatal error'));

        Vite::shouldReceive('asset')
            ->with('resources/js/vendor-exports/inertia.ts')
            ->andReturn('/build/assets/inertia-JKL012.js');

        Vite::shouldReceive('asset')
            ->with('resources/js/vendor-exports/unhead.ts')
            ->andReturn('/build/assets/unhead-MNO345.js');

        $component = new ModuleImportMap();
        $view = $component->render();
        $html = $view->render();

        $this->assertStringContainsString('"vue": "/build/assets/vue-ABC123.js"', $html);
        $this->assertStringNotContainsString('"pinia"', $html);
        $this->assertStringNotContainsString('"vue-i18n"', $html);
        $this->assertStringContainsString('"@inertiajs/vue3": "/build/assets/inertia-JKL012.js"', $html);
        $this->assertStringContainsString('"@unhead/vue": "/build/assets/unhead-MNO345.js"', $html);
    }
}
