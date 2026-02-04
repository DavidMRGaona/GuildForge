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

        /** @var array<string, array{file?: string, name?: string}>|null $manifest */
        $manifest = json_decode($content, true);

        if ($manifest === null) {
            return [];
        }

        $vendorVue = $this->findChunkByName($manifest, 'vendor-vue');
        $vendorInertia = $this->findChunkByName($manifest, 'vendor-inertia');

        $imports = [];

        if ($vendorVue !== null) {
            // vue, pinia, vue-i18n are bundled together in the vendor-vue chunk
            $imports['vue'] = "/build/{$vendorVue}";
            $imports['pinia'] = "/build/{$vendorVue}";
            $imports['vue-i18n'] = "/build/{$vendorVue}";
        }

        if ($vendorInertia !== null) {
            $imports['@inertiajs/vue3'] = "/build/{$vendorInertia}";
        }

        return $imports;
    }

    /**
     * Find a chunk file path by its name in the manifest.
     *
     * @param  array<string, array{file?: string, name?: string}>  $manifest
     */
    private function findChunkByName(array $manifest, string $name): ?string
    {
        foreach ($manifest as $entry) {
            if (isset($entry['name'], $entry['file']) && $entry['name'] === $name) {
                return $entry['file'];
            }
        }

        return null;
    }
}
