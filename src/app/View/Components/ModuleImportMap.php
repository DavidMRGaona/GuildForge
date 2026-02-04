<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Generates an import map for module externals.
 *
 * Modules are built with vue, pinia, vue-i18n, and @inertiajs/vue3 marked as
 * external dependencies. The browser needs an import map to resolve these
 * bare import specifiers to the actual vendor chunk files from the main app.
 */
final class ModuleImportMap extends Component
{
    public function render(): View
    {
        return view('components.module-import-map', [
            'imports' => $this->getImportMap(),
        ]);
    }

    /**
     * Build import map from the main app's Vite manifest.
     *
     * Maps bare import specifiers (vue, pinia, etc.) to the built
     * vendor-export entry points that properly re-export all symbols.
     *
     * @return array<string, string>
     */
    private function getImportMap(): array
    {
        $manifestPath = public_path('build/manifest.json');

        if (! file_exists($manifestPath)) {
            return [];
        }

        $content = file_get_contents($manifestPath);

        if ($content === false) {
            return [];
        }

        /** @var array<string, array{file?: string}>|null $manifest */
        $manifest = json_decode($content, true);

        if ($manifest === null) {
            return [];
        }

        // Map vendor-exports entry points to their import specifiers
        $mappings = [
            'resources/js/vendor-exports/vue.ts' => 'vue',
            'resources/js/vendor-exports/pinia.ts' => 'pinia',
            'resources/js/vendor-exports/vue-i18n.ts' => 'vue-i18n',
            'resources/js/vendor-exports/inertia.ts' => '@inertiajs/vue3',
        ];

        $imports = [];

        foreach ($mappings as $entryPoint => $importSpecifier) {
            if (isset($manifest[$entryPoint]['file'])) {
                $imports[$importSpecifier] = '/build/' . $manifest[$entryPoint]['file'];
            }
        }

        return $imports;
    }
}
