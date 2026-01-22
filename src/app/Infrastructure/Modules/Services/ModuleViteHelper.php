<?php

declare(strict_types=1);

namespace App\Infrastructure\Modules\Services;

use App\Modules\ModuleLoader;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

final class ModuleViteHelper
{
    public function __construct(
        private readonly ModuleLoader $loader,
        private readonly string $modulesPath,
    ) {
    }

    /**
     * Generate Vite tags for a module's entry points.
     *
     * @param array<string> $entrypoints
     */
    public function __invoke(string $moduleName, array $entrypoints = []): HtmlString
    {
        if (! $this->loader->isLoaded($moduleName)) {
            return new HtmlString('');
        }

        $moduleStudly = Str::studly($moduleName);

        if (empty($entrypoints)) {
            $entrypoints = ["modules/{$moduleName}/resources/js/app.ts"];
        }

        // In development, use Vite dev server
        if ($this->isRunningHot()) {
            return $this->makeDevTags($entrypoints);
        }

        // In production, use built manifest
        return $this->makeProductionTags($moduleName, $entrypoints);
    }

    /**
     * Get the path to a module's manifest file.
     */
    public function manifestPath(string $moduleName): string
    {
        return public_path("build/modules/{$moduleName}/.vite/manifest.json");
    }

    /**
     * Check if a module has built assets.
     */
    public function hasBuiltAssets(string $moduleName): bool
    {
        return file_exists($this->manifestPath($moduleName));
    }

    /**
     * Get the URL for a module asset from the manifest.
     */
    public function asset(string $moduleName, string $path): ?string
    {
        $manifest = $this->getManifest($moduleName);

        if ($manifest === null) {
            return null;
        }

        $key = "modules/{$moduleName}/resources/{$path}";

        return $manifest[$key]['file'] ?? null;
    }

    /**
     * Check if Vite dev server is running.
     */
    private function isRunningHot(): bool
    {
        return file_exists(public_path('hot'));
    }

    /**
     * Generate development tags using Vite dev server.
     *
     * @param array<string> $entrypoints
     */
    private function makeDevTags(array $entrypoints): HtmlString
    {
        $hotFile = public_path('hot');
        $url = rtrim(file_get_contents($hotFile) ?: 'http://localhost:5173', '/');

        $tags = [];
        foreach ($entrypoints as $entry) {
            $tags[] = sprintf(
                '<script type="module" src="%s/%s"></script>',
                $url,
                ltrim($entry, '/')
            );
        }

        return new HtmlString(implode("\n", $tags));
    }

    /**
     * Generate production tags from manifest.
     *
     * @param array<string> $entrypoints
     */
    private function makeProductionTags(string $moduleName, array $entrypoints): HtmlString
    {
        $manifest = $this->getManifest($moduleName);

        if ($manifest === null) {
            return new HtmlString('');
        }

        $tags = [];
        foreach ($entrypoints as $entry) {
            $chunk = $manifest[$entry] ?? null;

            if ($chunk === null) {
                continue;
            }

            // Add CSS files
            if (isset($chunk['css'])) {
                foreach ($chunk['css'] as $css) {
                    $tags[] = sprintf(
                        '<link rel="stylesheet" href="/build/modules/%s/%s">',
                        $moduleName,
                        $css
                    );
                }
            }

            // Add JS file
            if (isset($chunk['file'])) {
                $tags[] = sprintf(
                    '<script type="module" src="/build/modules/%s/%s"></script>',
                    $moduleName,
                    $chunk['file']
                );
            }
        }

        return new HtmlString(implode("\n", $tags));
    }

    /**
     * Get the manifest for a module.
     *
     * @return array<string, mixed>|null
     */
    private function getManifest(string $moduleName): ?array
    {
        $manifestPath = $this->manifestPath($moduleName);

        if (! file_exists($manifestPath)) {
            return null;
        }

        $content = file_get_contents($manifestPath);

        if ($content === false) {
            return null;
        }

        return json_decode($content, true);
    }
}
