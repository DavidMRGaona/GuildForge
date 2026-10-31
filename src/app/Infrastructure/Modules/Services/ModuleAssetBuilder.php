<?php

declare(strict_types=1);

namespace App\Infrastructure\Modules\Services;

use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Exceptions\ModuleNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Throwable;

final readonly class ModuleAssetBuilder
{
    /**
     * @param  string|null  $buildRoot  Where scripts/build-module.sh runs, building
     *                                  <buildRoot>/modules/<name>; null is base_path()
     */
    public function __construct(
        private string $modulesPath,
        private ?string $buildRoot = null,
    ) {}

    /**
     * Check if a module has Vue components or pages that need building.
     */
    public function hasComponents(Module $module): bool
    {
        $modulePath = $this->resolveModulePath($module);

        foreach (['components', 'pages'] as $directory) {
            $path = $modulePath.'/resources/js/'.$directory;

            if (is_dir($path) && ((glob($path.'/*.vue') ?: []) !== [] || (glob($path.'/**/*.vue') ?: []) !== [])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a module has a vite.config.ts the core can build: one that calls
     * defineModuleConfig (the core's toolchain). A module's own Vite is not run.
     */
    public function hasBuildConfig(Module $module): bool
    {
        return $this->usesCoreToolchain($this->resolveModulePath($module));
    }

    /**
     * Check if a module has already been built.
     */
    public function hasBuiltAssets(Module $module): bool
    {
        $manifestPath = public_path('build/modules/'.$module->name()->value.'/manifest.json');

        return file_exists($manifestPath);
    }

    /**
     * Whether Node is available here to build module assets. The application and queue
     * containers run PHP only; in development the Node container builds them.
     */
    public function canBuild(): bool
    {
        try {
            return Process::timeout(30)->run(['npx', '--version'])->successful();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * The command that builds a module's assets in the development Node container.
     */
    public static function manualBuildCommand(string $moduleName): string
    {
        return "docker exec guildforge_node npm run build:module -- {$moduleName}";
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

        if (! file_exists($modulePath.'/vite.config.ts')) {
            Log::info("Module {$module->name()->value} has no build configuration, skipping asset build");

            return true;
        }

        if (! $this->hasComponents($module)) {
            Log::info("Module {$module->name()->value} has no Vue components, skipping asset build");

            return true;
        }

        if (! $this->usesCoreToolchain($modulePath)) {
            Log::error(
                "Cannot build assets for module {$module->name()->value}: its vite.config.ts does not call defineModuleConfig",
                ['hint' => 'Modules build with the core\'s toolchain: see stubs/modules/module/vite.config.ts.stub'],
            );

            return false;
        }

        if (! $force && $this->hasBuiltAssets($module)) {
            Log::info("Module {$module->name()->value} already has built assets, skipping (use --force to rebuild)");

            return true;
        }

        $missingEnvVars = $this->missingViteEnvVars();

        if ($missingEnvVars !== []) {
            Log::error(
                "Cannot build assets for module {$module->name()->value}: missing required Vite environment variables",
                ['missing' => $missingEnvVars],
            );

            return false;
        }

        $name = $module->name()->value;
        $buildRoot = $this->buildRoot ?? base_path();
        $builtPath = $buildRoot.'/modules/'.$name;

        // The script only takes the name: a module elsewhere (another MODULES_PATH or stored
        // path) would get its libraries installed in one directory and another one built
        if (realpath($modulePath) !== realpath($builtPath)) {
            Log::error(
                "Cannot build assets for module {$name}: the build script builds another directory",
                ['module_path' => $modulePath, 'built_path' => $builtPath],
            );

            return false;
        }

        Log::info("Building assets for module: {$name}");

        // Only the module's own runtime libraries: the build tools are the core's
        if (file_exists($modulePath.'/package.json') && ! is_dir($modulePath.'/node_modules')
            && ! $this->run(['npm', 'ci', '--no-audit', '--no-fund'], $modulePath, 'npm ci', $name)) {
            return false;
        }

        return $this->run(['sh', 'scripts/build-module.sh', $name], $buildRoot, 'build', $name, $this->viteEnvironment());
    }

    /**
     * Vite environment variables forwarded to the module build process.
     *
     * Vite resolves `import.meta.env.VITE_*` at build time from the `.env` file
     * in the build's root, the module directory, which has no `.env`, so the
     * values are passed through the environment of the build process instead.
     *
     * @return array<string, string>
     */
    public function viteEnvironment(): array
    {
        /** @var array<string, string|null> $configured */
        $configured = config('module-build.vite_env', []);

        return array_filter(
            $configured,
            static fn (?string $value): bool => $value !== null && $value !== '',
        );
    }

    /**
     * Required Vite variables that have no value in the current environment.
     *
     * @return list<string>
     */
    public function missingViteEnvVars(): array
    {
        /** @var list<string> $required */
        $required = config('module-build.required_vite_env', []);
        $available = $this->viteEnvironment();

        return array_values(
            array_filter($required, static fn (string $name): bool => ! isset($available[$name])),
        );
    }

    /**
     * Whether the module builds with the core's toolchain (vite.module.config.ts).
     */
    private function usesCoreToolchain(string $modulePath): bool
    {
        $config = $modulePath.'/vite.config.ts';

        return file_exists($config) && str_contains((string) file_get_contents($config), 'defineModuleConfig');
    }

    /**
     * Run a build step, logging its outcome.
     *
     * @param  list<string>  $command
     * @param  array<string, string>  $environment
     */
    private function run(array $command, string $path, string $step, string $moduleName, array $environment = []): bool
    {
        Log::info("Running {$step} for module: {$moduleName}");

        $result = Process::path($path)->env($environment)->timeout(300)->run($command);

        if ($result->failed()) {
            Log::error("{$step} failed for module {$moduleName}", [
                'output' => $result->output(),
                'errorOutput' => $result->errorOutput(),
            ]);

            return false;
        }

        Log::info("{$step} completed for module: {$moduleName}");

        return true;
    }

    /**
     * Resolve the path for a module.
     *
     * @throws ModuleNotFoundException
     */
    private function resolveModulePath(Module $module): string
    {
        return (new ModulePathResolver($this->modulesPath))->resolve($module);
    }
}
