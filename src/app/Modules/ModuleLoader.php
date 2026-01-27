<?php

declare(strict_types=1);

namespace App\Modules;

use App\Application\Authorization\DTOs\PermissionDefinitionDTO;
use App\Application\Authorization\Services\PermissionRegistryInterface;
use App\Application\Modules\DTOs\PermissionDTO;
use App\Application\Modules\Services\ModulePageRegistryInterface;
use App\Application\Modules\Services\ModuleRouteRegistryInterface;
use App\Application\Modules\Services\ModuleSlotRegistryInterface;
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
        $providerClass = $module->namespace().'\\'.$module->provider();
        $modulePath = $module->path();

        // Register autoloader for module namespace
        $this->registerAutoloader($module->namespace(), $modulePath.'/src');

        if (! class_exists($providerClass)) {
            return;
        }

        // ServiceProviders require the $app parameter - use direct instantiation
        /** @var ModuleServiceProvider $provider */
        $provider = new $providerClass($this->app);

        $this->app->register($provider);
        $this->loadedProviders[$module->name()->value] = $provider;

        // Register slots from the provider
        $this->registerProviderSlots($provider);

        // Register permissions from the provider
        $this->registerProviderPermissions($provider);

        // Register page prefixes from the provider
        $this->registerProviderPagePrefixes($provider);

        // Register routes from the provider
        $this->registerProviderRoutes($provider);
    }

    /**
     * Register slots from a module provider.
     */
    private function registerProviderSlots(ModuleServiceProvider $provider): void
    {
        $slots = $provider->registerSlots();

        if ($slots === []) {
            return;
        }

        if ($this->app->bound(ModuleSlotRegistryInterface::class)) {
            $slotRegistry = $this->app->make(ModuleSlotRegistryInterface::class);
            $slotRegistry->registerMany($slots);
        }
    }

    /**
     * Register permissions from a module provider.
     */
    private function registerProviderPermissions(ModuleServiceProvider $provider): void
    {
        $permissions = $provider->registerPermissions();

        if ($permissions === []) {
            return;
        }

        if (! $this->app->bound(PermissionRegistryInterface::class)) {
            return;
        }

        /** @var PermissionRegistryInterface $permissionRegistry */
        $permissionRegistry = $this->app->make(PermissionRegistryInterface::class);

        // Convert module PermissionDTO to PermissionDefinitionDTO
        $definitions = array_map(
            fn (PermissionDTO $dto): PermissionDefinitionDTO => $this->convertPermissionDTO($dto),
            $permissions
        );

        $permissionRegistry->registerMany($definitions);
    }

    /**
     * Register page prefixes from a module provider.
     */
    private function registerProviderPagePrefixes(ModuleServiceProvider $provider): void
    {
        $prefixes = $provider->registerPagePrefixes();

        if ($prefixes === []) {
            return;
        }

        if ($this->app->bound(ModulePageRegistryInterface::class)) {
            $pageRegistry = $this->app->make(ModulePageRegistryInterface::class);
            $pageRegistry->registerMany($prefixes);
        }
    }

    /**
     * Register routes from a module provider.
     */
    private function registerProviderRoutes(ModuleServiceProvider $provider): void
    {
        $routes = $provider->registerRoutes();

        if ($routes === []) {
            return;
        }

        if ($this->app->bound(ModuleRouteRegistryInterface::class)) {
            $routeRegistry = $this->app->make(ModuleRouteRegistryInterface::class);
            $routeRegistry->registerMany($routes);
        }
    }

    /**
     * Convert a module PermissionDTO to a PermissionDefinitionDTO.
     */
    private function convertPermissionDTO(PermissionDTO $dto): PermissionDefinitionDTO
    {
        // Parse the permission name to extract resource and action
        // Module permissions use format: "resource.action" (e.g., "announcements.view")
        $parts = explode('.', $dto->name);
        $resource = $parts[0];
        $action = $parts[1] ?? $dto->name;

        // Build the full key with module prefix
        $key = $dto->module !== null
            ? "{$dto->module}:{$dto->name}"
            : $dto->name;

        return new PermissionDefinitionDTO(
            key: $key,
            label: $dto->label,
            resource: $resource,
            action: $action,
            module: $dto->module,
            defaultRoles: $dto->roles,
        );
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
     * Get all loaded providers.
     *
     * @return array<string, ModuleServiceProvider>
     */
    public function getLoadedProviders(): array
    {
        return $this->loadedProviders;
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
            $namespace = rtrim($namespace, '\\').'\\';

            if (! str_starts_with($class, $namespace)) {
                return;
            }

            $relativeClass = substr($class, strlen($namespace));
            $file = $path.'/'.str_replace('\\', '/', $relativeClass).'.php';

            if (file_exists($file)) {
                require_once $file;
            }
        });
    }
}
