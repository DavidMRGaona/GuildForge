<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Application\Services\SettingsServiceInterface;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use App\Modules\ModuleServiceProvider;
use Filament\Http\Middleware\{Authenticate,
    AuthenticateSession,
    DisableBladeIconComponents,
    DispatchServingFilamentEvent
};
use Filament\Navigation\NavigationGroup;
use Filament\{Pages, Panel, PanelProvider, Widgets};
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\{AddQueuedCookiesToResponse, EncryptCookies};
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Throwable;

class AdminPanelProvider extends PanelProvider
{
    /**
     * Cache for enabled module names to avoid multiple repository calls.
     *
     * @var array<string>|null
     */
    private ?array $enabledModuleNamesCache = null;

    /**
     * Track registered module autoloaders to avoid duplicates.
     *
     * @var array<string, bool>
     */
    private array $registeredAutoloaders = [];

    public function panel(Panel $panel): Panel
    {
        $panel = $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName(config('app.name') . ' Admin')
            ->brandLogo(fn(): ?string => $this->getBrandLogo('site_logo_light'))
            ->darkModeBrandLogo(fn(): ?string => $this->getBrandLogo('site_logo_dark'))
            ->brandLogoHeight('2.5rem')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->darkMode(true)
            ->navigationGroups($this->getNavigationGroups())
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);

        // Discover resources from enabled modules
        $this->discoverModuleResources($panel);

        return $panel;
    }

    private function getBrandLogo(string $key): ?string
    {
        try {
            $settingsService = app(SettingsServiceInterface::class);
            $logoPath = (string)$settingsService->get($key, '');

            if ($logoPath === '') {
                return null;
            }

            return Storage::disk('images')->url($logoPath);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Get all navigation groups (core + modules).
     *
     * @return array<NavigationGroup>
     */
    private function getNavigationGroups(): array
    {
        // Core navigation groups with sort order
        $coreGroups = [
            'Contenido' => ['sort' => 10],
            'Páginas' => ['sort' => 30],
            'Configuración' => ['sort' => 40],
            'Administración' => ['sort' => 50],
        ];

        // Collect navigation groups from modules
        $moduleGroups = $this->collectModuleNavigationGroups();

        // Merge groups (modules can add new groups but not override core)
        $allGroups = array_merge($moduleGroups, $coreGroups);

        // Sort by 'sort' key (default to 100), then alphabetically by label for ties
        uksort($allGroups, static function (string $labelA, string $labelB) use ($allGroups): int {
            $sortA = $allGroups[$labelA]['sort'] ?? 100;
            $sortB = $allGroups[$labelB]['sort'] ?? 100;

            // Primary: sort order
            if ($sortA !== $sortB) {
                return $sortA <=> $sortB;
            }

            // Secondary: alphabetical by label
            return $labelA <=> $labelB;
        });

        // Convert to NavigationGroup objects
        $navigationGroups = [];
        foreach ($allGroups as $label => $options) {
            $group = NavigationGroup::make($label);

            if (isset($options['icon'])) {
                $group->icon($options['icon']);
            }

            $navigationGroups[] = $group;
        }

        return $navigationGroups;
    }

    /**
     * Collect navigation groups from enabled modules.
     *
     * @return array<string, array{icon?: string, sort?: int}>
     */
    private function collectModuleNavigationGroups(): array
    {
        $groups = [];
        $modulesPath = config('modules.path', base_path('modules'));

        if (!is_dir($modulesPath)) {
            return $groups;
        }

        // Get list of enabled module names
        $enabledModules = $this->getEnabledModuleNames();

        // Scan module directories for service providers
        $moduleDirectories = glob($modulesPath . '/*', GLOB_ONLYDIR);
        if ($moduleDirectories === false) {
            return $groups;
        }

        foreach ($moduleDirectories as $modulePath) {
            $moduleName = basename($modulePath);

            // Skip disabled modules
            if (!in_array($moduleName, $enabledModules, true)) {
                continue;
            }

            $studlyName = str_replace('-', '', ucwords($moduleName, '-'));

            // Find the service provider file
            $providerFile = $modulePath . '/src/' . $studlyName . 'ServiceProvider.php';
            if (!file_exists($providerFile)) {
                continue;
            }

            // Register autoloader and load the provider
            $this->registerModuleAutoloader("Modules\\{$studlyName}", $modulePath . '/src');

            $providerClass = "Modules\\{$studlyName}\\{$studlyName}ServiceProvider";

            // Load the provider file
            require_once $providerFile;

            if (!class_exists($providerClass)) {
                continue;
            }

            try {
                // Load module translations first
                $langPath = $modulePath . '/lang';
                if (is_dir($langPath)) {
                    app('translator')->addNamespace($moduleName, $langPath);
                }

                /** @var ModuleServiceProvider $provider */
                $provider = new $providerClass(app());
                $moduleGroups = $provider->registerNavigationGroups();

                foreach ($moduleGroups as $label => $options) {
                    if (!isset($groups[$label])) {
                        $groups[$label] = $options;
                    }
                }
            } catch (Throwable) {
                // Skip modules that fail to instantiate
            }
        }

        return $groups;
    }

    /**
     * Get the names of all enabled modules.
     *
     * @return array<string>
     */
    private function getEnabledModuleNames(): array
    {
        // Return cached value if available
        if ($this->enabledModuleNamesCache !== null) {
            return $this->enabledModuleNamesCache;
        }

        try {
            if (!app()->bound(ModuleRepositoryInterface::class)) {
                logger()->debug('[AdminPanelProvider] ModuleRepositoryInterface not bound yet');

                return $this->enabledModuleNamesCache = [];
            }

            $repository = app(ModuleRepositoryInterface::class);
            $enabledModules = $repository->enabled()->all();

            $names = array_map(
                fn($module) => $module->name()->value,
                $enabledModules
            );

            logger()->debug('[AdminPanelProvider] Enabled modules detected', ['modules' => $names]);

            return $this->enabledModuleNamesCache = $names;
        } catch (Throwable $e) {
            logger()->debug('[AdminPanelProvider] Failed to get enabled modules', ['error' => $e->getMessage()]);

            return $this->enabledModuleNamesCache = [];
        }
    }

    /**
     * Register SPL autoloader for a module namespace (only once per namespace).
     */
    private function registerModuleAutoloader(string $namespace, string $path): void
    {
        // Skip if already registered
        if (isset($this->registeredAutoloaders[$namespace])) {
            return;
        }

        $this->registeredAutoloaders[$namespace] = true;

        // Pre-calculate namespace prefix (done once at registration, not per class load)
        $prefix = rtrim($namespace, '\\') . '\\';
        $prefixLength = strlen($prefix);

        spl_autoload_register(static function (string $class) use ($prefix, $prefixLength, $path): void {
            if (!str_starts_with($class, $prefix)) {
                return;
            }

            $file = $path . '/' . str_replace('\\', '/', substr($class, $prefixLength)) . '.php';

            if (file_exists($file)) {
                require_once $file;
            }
        });
    }

    /**
     * Discover and register Filament resources from enabled modules.
     */
    private function discoverModuleResources(Panel $panel): void
    {
        $modulesPath = config('modules.path', base_path('modules'));

        if (!is_dir($modulesPath)) {
            return;
        }

        // Get list of enabled module names
        $enabledModules = $this->getEnabledModuleNames();

        $resources = [];

        $resourcesPaths = glob($modulesPath . '/*/src/Filament/Resources');
        if ($resourcesPaths === false) {
            return;
        }

        foreach ($resourcesPaths as $resourcesPath) {
            if (!is_dir($resourcesPath)) {
                continue;
            }

            // Extract module name from path: modules/{module-name}/src/Filament/Resources
            $modulePath = dirname($resourcesPath, 3);
            $moduleName = basename($modulePath);

            // Skip disabled modules
            if (!in_array($moduleName, $enabledModules, true)) {
                continue;
            }

            $studlyName = str_replace('-', '', ucwords($moduleName, '-'));
            $namespace = "Modules\\{$studlyName}\\Filament\\Resources";

            // Register SPL autoloader for this module
            $this->registerModuleAutoloader("Modules\\{$studlyName}", $modulePath . '/src');

            // Find all resource files
            $resourceFiles = glob($resourcesPath . '/*Resource.php');
            if ($resourceFiles === false) {
                continue;
            }

            foreach ($resourceFiles as $file) {
                $className = basename($file, '.php');
                $resourceClass = $namespace . '\\' . $className;

                // Load the class file
                require_once $file;

                if (class_exists($resourceClass)) {
                    $resources[] = $resourceClass;
                }
            }
        }

        if ($resources !== []) {
            $panel->resources($resources);
        }
    }
}
