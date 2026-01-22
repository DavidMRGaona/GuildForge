<?php

declare(strict_types=1);

namespace App\Modules;

use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Cache;

final class ModuleLoader
{
    /** @var array<string, ModuleServiceProvider> */
    private array $loadedProviders = [];

    public function __construct(
        private readonly Application $app,
        private readonly ModuleRepositoryInterface $repository,
    ) {
    }

    /**
     * Boot all enabled modules.
     */
    public function boot(): void
    {
        $modules = $this->getEnabledModules();

        foreach ($modules as $module) {
            $this->bootModule($module);
        }
    }

    /**
     * Boot a specific module.
     */
    public function bootModule(Module $module): void
    {
        $providerClass = $module->namespace() . '\\' . $module->provider();
        $modulePath = $module->path();

        // Register autoloader for module namespace
        $this->registerAutoloader($module->namespace(), $modulePath . '/src');

        if (!class_exists($providerClass)) {
            return;
        }

        /** @var ModuleServiceProvider $provider */
        $provider = $this->app->make($providerClass);

        $this->app->register($provider);
        $this->loadedProviders[$module->name()->value] = $provider;
    }

    /**
     * Get the provider instance for a loaded module.
     */
    public function getProvider(string $moduleName): ?ModuleServiceProvider
    {
        return $this->loadedProviders[$moduleName] ?? null;
    }

    /**
     * Check if a module is loaded.
     */
    public function isLoaded(string $moduleName): bool
    {
        return isset($this->loadedProviders[$moduleName]);
    }

    /**
     * Get all loaded module names.
     *
     * @return array<string>
     */
    public function loadedModules(): array
    {
        return array_keys($this->loadedProviders);
    }

    /**
     * @return array<Module>
     */
    private function getEnabledModules(): array
    {
        if (config('modules.cache.enabled', false)) {
            return Cache::remember(
                config('modules.cache.key', 'modules.enabled'),
                config('modules.cache.ttl', 3600),
                fn () => $this->repository->enabled()->all()
            );
        }

        return $this->repository->enabled()->all();
    }

    /**
     * Register SPL autoloader for module namespace.
     */
    private function registerAutoloader(string $namespace, string $path): void
    {
        spl_autoload_register(function (string $class) use ($namespace, $path): void {
            $namespace = rtrim($namespace, '\\') . '\\';

            if (!str_starts_with($class, $namespace)) {
                return;
            }

            $relativeClass = substr($class, strlen($namespace));
            $file = $path . '/' . str_replace('\\', '/', $relativeClass) . '.php';

            if (file_exists($file)) {
                require_once $file;
            }
        });
    }
}
