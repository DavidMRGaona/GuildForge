<?php

declare(strict_types=1);

namespace App\Modules;

use App\Application\Authorization\DTOs\PermissionDefinitionDTO;
use App\Application\Authorization\Services\PermissionRegistryInterface;
use App\Application\Calendar\Services\CalendarAttendanceSourceRegistryInterface;
use App\Application\Calendar\Services\CalendarSourceRegistryInterface;
use App\Application\Knowledge\Services\KnowledgeCapabilityRegistryInterface;
use App\Application\Mail\Services\MailCategoryRegistryInterface;
use App\Application\Modules\DTOs\PermissionDTO;
use App\Application\Modules\Services\EnabledModulesResolverInterface;
use App\Application\Modules\Services\ModuleNavigationRegistryInterface;
use App\Application\Modules\Services\ModulePageRegistryInterface;
use App\Application\Modules\Services\ModuleRouteRegistryInterface;
use App\Application\Modules\Services\ModuleSlotRegistryInterface;
use App\Application\Publications\Services\PublicationSourceRegistryInterface;
use App\Application\Search\Services\SearchSourceRegistryInterface;
use App\Application\Surveys\Services\SurveySubjectSourceRegistryInterface;
use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Exceptions\ModuleNotFoundException;
use App\Infrastructure\Modules\Services\ModulePathResolver;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Log;

final class ModuleLoader
{
    /** @var array<string, ModuleServiceProvider> */
    private array $loadedProviders = [];

    public function __construct(
        private readonly Application $app,
        private readonly EnabledModulesResolverInterface $resolver,
        private readonly ModulePathResolver $paths = new ModulePathResolver,
    ) {}

    /**
     * Boot all enabled modules.
     */
    public function boot(): void
    {
        $modules = $this->getEnabledModules();

        foreach ($modules as $module) {
            try {
                $this->bootModule($module);
            } catch (\Throwable $e) {
                Log::error('[ModuleLoader] Failed to boot module', [
                    'module' => $module->name()->value,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Boot a specific module.
     */
    public function bootModule(Module $module): void
    {
        $providerClass = $module->namespace().'\\'.$module->provider();
        try {
            $modulePath = $this->paths->resolve($module);
        } catch (ModuleNotFoundException) {
            // Unreachable for an approved module: without module.json in the modules
            // directory the compatibility check rejects it (shown on the panel banner)
            return;
        }

        // Register autoloader for module namespace
        $this->registerAutoloader($module->namespace(), $modulePath.'/src');

        if (! class_exists($providerClass)) {
            return;
        }

        // ServiceProviders require the $app parameter - use direct instantiation
        /** @var ModuleServiceProvider $provider */
        $provider = new $providerClass($this->app);

        $this->registerTranslationNamespace($provider->moduleName(), $modulePath.'/lang');

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

        // Register calendar sources from the provider
        $this->registerProviderCalendarSources($provider, $module->name()->value);

        // Register calendar attendance sources from the provider
        $this->registerProviderCalendarAttendanceSources($provider, $module->name()->value);

        // Register indexed search sources from the provider
        $this->registerProviderSearchSources($provider, $module->name()->value);

        // Register survey subject sources from the provider
        $this->registerProviderSurveySubjectSources($provider, $module->name()->value);

        // Register publication sources from the provider
        $this->registerProviderPublicationSources($provider, $module->name()->value);

        // Register knowledge capabilities from the provider
        $this->registerProviderKnowledgeCapabilities($provider, $module->name()->value);

        // Register mail categories from the provider
        $this->registerProviderMailCategories($provider, $module->name()->value);

        // Register navigation from the provider
        $this->registerProviderNavigation($provider);
    }

    /**
     * The module's hooks (permissions, navigation…) translate their labels as soon as they are
     * read. While the application boots, a provider registered from another provider's boot()
     * is only booted after that one finishes, so the module's own loadTranslations() has not
     * run yet; a key asked before its namespace exists is cached as an empty group and stays
     * untranslated for the whole request. Registering the namespace here, before the hooks,
     * avoids it (core#429).
     */
    private function registerTranslationNamespace(string $namespace, string $langPath): void
    {
        if (is_dir($langPath)) {
            $this->app->make('translator')->addNamespace($namespace, $langPath);
        }
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
            $this->convertPermissionDTO(...),
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
     * Register calendar sources from a module provider.
     */
    private function registerProviderCalendarSources(ModuleServiceProvider $provider, string $moduleName): void
    {
        $sources = $provider->registerCalendarSources();

        if ($sources === []) {
            return;
        }

        if ($this->app->bound(CalendarSourceRegistryInterface::class)) {
            $sourceRegistry = $this->app->make(CalendarSourceRegistryInterface::class);
            $sourceRegistry->registerMany($sources, $moduleName);
        }
    }

    /**
     * Register calendar attendance sources from a module provider.
     */
    private function registerProviderCalendarAttendanceSources(ModuleServiceProvider $provider, string $moduleName): void
    {
        $sources = $provider->registerCalendarAttendanceSources();

        if ($sources === []) {
            return;
        }

        if ($this->app->bound(CalendarAttendanceSourceRegistryInterface::class)) {
            $this->app->make(CalendarAttendanceSourceRegistryInterface::class)->registerMany($sources, $moduleName);
        }
    }

    /**
     * Register indexed search sources from a module provider.
     */
    private function registerProviderSearchSources(ModuleServiceProvider $provider, string $moduleName): void
    {
        $sources = $provider->registerSearchSources();

        if ($sources === []) {
            return;
        }

        if ($this->app->bound(SearchSourceRegistryInterface::class)) {
            $this->app->make(SearchSourceRegistryInterface::class)->registerMany($sources, $moduleName);
        }
    }

    /**
     * Register survey subject sources from a module provider.
     */
    private function registerProviderSurveySubjectSources(ModuleServiceProvider $provider, string $moduleName): void
    {
        $sources = $provider->registerSurveySubjectSources();

        if ($sources === []) {
            return;
        }

        if ($this->app->bound(SurveySubjectSourceRegistryInterface::class)) {
            $sourceRegistry = $this->app->make(SurveySubjectSourceRegistryInterface::class);
            $sourceRegistry->registerMany($sources, $moduleName);
        }
    }

    /**
     * Register publication sources from a module provider.
     */
    private function registerProviderPublicationSources(ModuleServiceProvider $provider, string $moduleName): void
    {
        $sources = $provider->registerPublicationSources();

        if ($sources === []) {
            return;
        }

        if ($this->app->bound(PublicationSourceRegistryInterface::class)) {
            $this->app->make(PublicationSourceRegistryInterface::class)->registerMany($sources, $moduleName);
        }
    }

    /**
     * Register knowledge capabilities from a module provider.
     */
    private function registerProviderKnowledgeCapabilities(ModuleServiceProvider $provider, string $moduleName): void
    {
        $capabilities = $provider->registerKnowledgeCapabilities();

        if ($capabilities === []) {
            return;
        }

        if ($this->app->bound(KnowledgeCapabilityRegistryInterface::class)) {
            $this->app->make(KnowledgeCapabilityRegistryInterface::class)->registerMany($capabilities, $moduleName);
        }
    }

    /**
     * Register mail categories from a module provider, under the module's prefix.
     */
    private function registerProviderMailCategories(ModuleServiceProvider $provider, string $moduleName): void
    {
        $categories = $provider->registerMailCategories();

        if ($categories === []) {
            return;
        }

        if ($this->app->bound(MailCategoryRegistryInterface::class)) {
            $this->app->make(MailCategoryRegistryInterface::class)->registerMany($categories, $moduleName);
        }
    }

    /**
     * Register navigation from a module provider.
     */
    private function registerProviderNavigation(ModuleServiceProvider $provider): void
    {
        $navigation = $provider->registerNavigation();

        if ($navigation === []) {
            return;
        }

        if ($this->app->bound(ModuleNavigationRegistryInterface::class)) {
            $navigationRegistry = $this->app->make(ModuleNavigationRegistryInterface::class);
            $navigationRegistry->registerMany($navigation);
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
            description: $dto->description,
            group: $dto->group,
            adminOnly: $dto->adminOnly,
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
     * Enabled and compatible modules; the resolver owns the module cache.
     *
     * @return array<Module>
     */
    private function getEnabledModules(): array
    {
        return $this->resolver->modules();
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
