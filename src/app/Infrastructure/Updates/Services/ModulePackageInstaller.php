<?php

declare(strict_types=1);

namespace App\Infrastructure\Updates\Services;

use App\Application\Updates\Services\ModulePackageInstallerInterface;
use App\Domain\Updates\Exceptions\UpdateException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Staging and previous-version directories live inside the modules directory and
 * start with a dot: rename() only works within one mount point (production
 * bind-mounts modules/ and storage/ separately) and discovery skips dot directories.
 */
final class ModulePackageInstaller implements ModulePackageInstallerInterface
{
    public function stage(string $zipPath, string $moduleName): string
    {
        $staging = $this->modulesPath()."/.staging-{$moduleName}-".Str::lower(Str::random(8));

        try {
            $root = $this->validatedRoot($zipPath, $moduleName);

            $zip = new ZipArchive;
            if ($zip->open($zipPath) !== true || ! $zip->extractTo($staging)) {
                throw UpdateException::extractionFailed($moduleName, 'Cannot extract the release package');
            }
            $zip->close();

            $stagedRoot = "{$staging}/{$root}";
            $this->assertManifestName($stagedRoot, $moduleName);

            return $stagedRoot;
        } catch (\Throwable $e) {
            $this->discard($staging);

            throw $e instanceof UpdateException
                ? $e
                : UpdateException::extractionFailed($moduleName, $e->getMessage());
        }
    }

    public function swap(string $stagedRoot, string $moduleName): string
    {
        $target = $this->modulesPath()."/{$moduleName}";
        $previous = $this->modulesPath()."/.previous-{$moduleName}-".Str::lower(Str::random(8));

        if (! @rename($target, $previous)) {
            throw UpdateException::extractionFailed($moduleName, 'Cannot move the installed version aside');
        }

        if (! @rename($stagedRoot, $target)) {
            @rename($previous, $target);

            throw UpdateException::extractionFailed($moduleName, 'Cannot move the new version into place');
        }

        $this->discard(dirname($stagedRoot));

        return $previous;
    }

    public function revert(string $previousPath, string $moduleName): void
    {
        $target = $this->modulesPath()."/{$moduleName}";

        if (File::isDirectory($target) && ! File::deleteDirectory($target)) {
            throw UpdateException::rollbackFailed($moduleName, 'Cannot remove the new version');
        }

        if (! @rename($previousPath, $target)) {
            throw UpdateException::rollbackFailed($moduleName, 'Cannot restore the previous version');
        }
    }

    public function discard(string $path): void
    {
        if (File::isDirectory($path)) {
            File::deleteDirectory($path);
        }
    }

    public function cleanupLeftovers(string $moduleName): void
    {
        foreach (['staging', 'previous'] as $kind) {
            foreach (glob($this->modulesPath()."/.{$kind}-{$moduleName}-*", GLOB_ONLYDIR) ?: [] as $path) {
                $this->discard($path);
            }
        }
    }

    /**
     * Inspect the entries before extracting anything: exactly one top-level
     * directory holding module.json, and no path that escapes the staging dir.
     */
    private function validatedRoot(string $zipPath, string $moduleName): string
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw UpdateException::extractionFailed($moduleName, 'Cannot open the release package');
        }

        $roots = [];
        $hasManifest = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = (string) $zip->getNameIndex($i);

            if (str_starts_with($entry, '/') || str_contains($entry, '\\') || preg_match('#(^|/)\.\.(/|$)#', $entry) === 1) {
                $zip->close();

                throw UpdateException::extractionFailed($moduleName, "Unsafe path in package: {$entry}");
            }

            $root = explode('/', $entry, 2)[0];
            $roots[$root] = true;

            if ($entry === "{$root}/module.json") {
                $hasManifest[$root] = true;
            }
        }

        $zip->close();

        if (count($roots) !== 1) {
            throw UpdateException::extractionFailed($moduleName, 'The package must contain a single module folder');
        }

        $root = (string) array_key_first($roots);

        if (! isset($hasManifest[$root])) {
            throw UpdateException::extractionFailed($moduleName, 'The package has no module.json');
        }

        return $root;
    }

    private function assertManifestName(string $stagedRoot, string $moduleName): void
    {
        /** @var array<string, mixed>|null $manifest */
        $manifest = json_decode((string) File::get("{$stagedRoot}/module.json"), true);

        if (! is_array($manifest) || ($manifest['name'] ?? null) !== $moduleName) {
            throw UpdateException::extractionFailed($moduleName, 'The package belongs to a different module');
        }
    }

    private function modulesPath(): string
    {
        return rtrim((string) config('modules.path', base_path('modules')), '/');
    }
}
