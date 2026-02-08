<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Vite;
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
     * Uses Vite::asset() to resolve paths through the same mechanism
     * as @vite(), making it resilient to manifest location changes
     * across Vite versions.
     *
     * @return array<string, string>
     */
    private function getImportMap(): array
    {
        $mappings = [
            // Vendor exports
            'resources/js/vendor-exports/vue.ts' => 'vue',
            'resources/js/vendor-exports/pinia.ts' => 'pinia',
            'resources/js/vendor-exports/vue-i18n.ts' => 'vue-i18n',
            'resources/js/vendor-exports/inertia.ts' => '@inertiajs/vue3',
            // App exports for @/ imports used by modules
            'resources/js/app-exports/cloudinary.ts' => '@/utils/cloudinary',
            'resources/js/app-exports/confirm-dialog.ts' => '@/components/ui/ConfirmDialog.vue',
            'resources/js/app-exports/form.ts' => '@/components/form',
        ];

        $imports = [];

        foreach ($mappings as $entryPoint => $importSpecifier) {
            try {
                $imports[$importSpecifier] = Vite::asset($entryPoint);
            } catch (\Throwable) {
                // Entry not in manifest or manifest not found — skip
            }
        }

        return $imports;
    }
}
