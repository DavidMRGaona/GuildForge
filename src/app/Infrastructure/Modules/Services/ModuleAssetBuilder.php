<?php

declare(strict_types=1);

namespace App\Infrastructure\Modules\Services;

use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Exceptions\ModuleNotFoundException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

final readonly class ModuleAssetBuilder
{
    public function __construct(
        private string $modulesPath,
    ) {
    }

    /**
     * Check if a module has Vue components that need building.
     */
    public function hasComponents(Module $module): bool
    {
        $modulePath = $this->resolveModulePath($module);
        $componentsPath = $modulePath.'/resources/js/components';

        if (! is_dir($componentsPath)) {
            return false;
        }

        $vueFiles = glob($componentsPath.'/**/*.vue') ?: [];
        $rootVueFiles = glob($componentsPath.'/*.vue') ?: [];

        return count($vueFiles) > 0 || count($rootVueFiles) > 0;
    }

    /**
     * Check if a module has a package.json for building assets.
     */
    public function hasBuildConfig(Module $module): bool
    {
        $modulePath = $this->resolveModulePath($module);

        return file_exists($modulePath.'/package.json')
            && file_exists($modulePath.'/vite.config.ts');
    }

    /**
     * Check if a module has already been built.
     */
    public function hasBuiltAssets(Module $module): bool
    {
        $manifestPath = public_path('build/modules/'.$module->name()->value.'/.vite/manifest.json');

        return file_exists($manifestPath);
    }

    /**
     * Build assets for a module.
     *
     * @param  Module  $module  The module to build
     * @param  bool  $force  Force rebuild even if assets already exist
     * @return bool True if build was successful
     *
     * @throws ModuleNotFoundException If the module directory does not exist
     */
    public function build(Module $module, bool $force = false): bool
    {
        $modulePath = $this->resolveModulePath($module);

        if (! $this->hasBuildConfig($module)) {
            Log::info("Module {$module->name()->value} has no build configuration, skipping asset build");

            return true;
        }

        if (! $this->hasComponents($module)) {
            Log::info("Module {$module->name()->value} has no Vue components, skipping asset build");

            return true;
        }

        if (! $force && $this->hasBuiltAssets($module)) {
            Log::info("Module {$module->name()->value} already has built assets, skipping (use --force to rebuild)");

            return true;
        }

        Log::info("Building assets for module: {$module->name()->value}");

        // Install dependencies if node_modules doesn't exist
        if (! is_dir($modulePath.'/node_modules')) {
            if (! $this->runNpmInstall($modulePath, $module->name()->value)) {
                return false;
            }
        }

        // Run the build
        return $this->runNpmBuild($modulePath, $module->name()->value);
    }

    /**
     * Run npm install in the module directory.
     */
    private function runNpmInstall(string $modulePath, string $moduleName): bool
    {
        Log::info("Running npm install for module: {$moduleName}");

        $process = new Process(['npm', 'install'], $modulePath);
        $process->setTimeout(300); // 5 minutes

        try {
            $process->mustRun();
            Log::info("npm install completed for module: {$moduleName}");

            return true;
        } catch (ProcessFailedException $e) {
            Log::error("npm install failed for module {$moduleName}: {$e->getMessage()}", [
                'output' => $process->getOutput(),
                'errorOutput' => $process->getErrorOutput(),
            ]);

            return false;
        }
    }

    /**
     * Run npm build in the module directory.
     */
    private function runNpmBuild(string $modulePath, string $moduleName): bool
    {
        Log::info("Running npm build for module: {$moduleName}");

        $process = new Process(['npm', 'run', 'build'], $modulePath);
        $process->setTimeout(300); // 5 minutes

        try {
            $process->mustRun();
            Log::info("npm build completed for module: {$moduleName}");

            return true;
        } catch (ProcessFailedException $e) {
            Log::error("npm build failed for module {$moduleName}: {$e->getMessage()}", [
                'output' => $process->getOutput(),
                'errorOutput' => $process->getErrorOutput(),
            ]);

            return false;
        }
    }

    /**
     * Resolve the path for a module.
     *
     * @throws ModuleNotFoundException
     */
    private function resolveModulePath(Module $module): string
    {
        $modulePath = $module->path();

        // Fall back to default path if module path is not set
        if (! is_dir($modulePath)) {
            $modulePath = $this->modulesPath.'/'.$module->name()->value;
        }

        if (! is_dir($modulePath)) {
            throw ModuleNotFoundException::withName($module->name()->value);
        }

        return $modulePath;
    }
}
