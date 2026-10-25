<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Vite;
use Illuminate\View\Component;

/**
 * Generates the import map that serves the core's public frontend API to modules.
 *
 * Module builds keep the shared packages (vue, pinia, vue-i18n, @inertiajs/vue3, @unhead/vue,
 * FullCalendar) and the public entries of resources/js out of their bundles; the browser
 * resolves those specifiers here to the app's own chunks, so the page loads each of them once
 * and every module uses the installed core. The list is resources/js/public-api.json, which the
 * core's build, scripts/verify-build.mjs and the module build read too.
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
     * Resolve every specifier of the list through the Vite manifest.
     *
     * Uses Vite::asset() to resolve paths through the same mechanism as @vite(), making it
     * resilient to manifest location changes. An entry the manifest lacks is left out.
     *
     * @return array<string, string>
     */
    private function getImportMap(): array
    {
        $imports = [];

        foreach ($this->publicApi() as $importSpecifier => $entryPoint) {
            try {
                $imports[$importSpecifier] = Vite::asset($entryPoint);
            } catch (\Throwable) {
                // Entry not in manifest or manifest not found — skip
            }
        }

        return $imports;
    }

    /**
     * The shared packages and the public entries, specifier => Vite entry.
     *
     * @return array<string, string>
     */
    private function publicApi(): array
    {
        /** @var array{packages: array<string, string>, entries: array<string, string>} $list */
        $list = json_decode((string) file_get_contents(resource_path('js/public-api.json')), true, flags: JSON_THROW_ON_ERROR);

        return [...$list['packages'], ...$list['entries']];
    }
}
