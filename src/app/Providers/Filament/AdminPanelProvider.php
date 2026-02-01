<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Application\Services\SettingsServiceInterface;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use App\Modules\ModuleServiceProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\DB;
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
            ->brandName(config('app.name').' Admin')
            ->brandLogo(fn (): ?string => $this->getBrandLogo('site_logo_light'))
            ->darkModeBrandLogo(fn (): ?string => $this->getBrandLogo('site_logo_dark'))
            ->brandLogoHeight('2.5rem')
            ->favicon(fn (): string => $this->getFavicon())
            ->colors([
                'primary' => $this->getPrimaryColor(),
            ])
            ->darkMode(true)
            ->navigationGroups($this->getNavigationGroups())
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
                ...$this->discoverModulePages(),
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets($this->discoverModuleWidgets())
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
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => Blade::render('
                    <style>
                        /* Time picker input wrapper - focus ring with Filament primary color */
                        .time-picker-input-wrapper:focus-within {
                            --tw-ring-offset-shadow: var(--tw-ring-inset) 0 0 0 var(--tw-ring-offset-width) var(--tw-ring-offset-color);
                            --tw-ring-shadow: var(--tw-ring-inset) 0 0 0 calc(2px + var(--tw-ring-offset-width)) var(--tw-ring-color);
                            box-shadow: var(--tw-ring-offset-shadow), var(--tw-ring-shadow), var(--tw-shadow, 0 0 #0000);
                            --tw-ring-color: rgb(var(--primary-600));
                        }

                        /* Time wheel picker - iOS style with infinite scroll */

                        /* Header with background */
                        .time-wheel-header {
                            background: rgb(249 250 251);
                        }
                        .dark .time-wheel-header {
                            background: rgb(55 65 81);
                        }

                        .time-wheel-container {
                            position: relative;
                            height: 160px; /* 5 items visible */
                        }

                        /* Scrollable wheel */
                        .time-wheel {
                            height: 100%;
                            overflow-y: scroll;
                            -webkit-overflow-scrolling: touch;
                            scrollbar-width: none;
                            -ms-overflow-style: none;
                        }
                        .time-wheel::-webkit-scrollbar {
                            display: none;
                        }

                        /* Each time item */
                        .time-wheel-item {
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            height: 32px;
                            width: 100%;
                            font-size: 1rem;
                            font-weight: 500;
                            color: rgb(55 65 81);
                            background: transparent;
                            border: none;
                            cursor: pointer;
                            transition: color 0.15s ease;
                        }
                        .time-wheel-item:hover {
                            color: rgb(17 24 39);
                        }
                        .dark .time-wheel-item {
                            color: rgb(156 163 175);
                        }
                        .dark .time-wheel-item:hover {
                            color: rgb(229 231 235);
                        }

                        /* Selection indicator bar - centered at 64px (2 items from top) */
                        .time-wheel-indicator {
                            position: absolute;
                            top: 64px;
                            left: 0;
                            right: 0;
                            height: 32px;
                            border-top: 1px solid rgb(209 213 219);
                            border-bottom: 1px solid rgb(209 213 219);
                            background: rgba(249 250 251 / 0.5);
                            pointer-events: none;
                            z-index: 1;
                        }
                        .dark .time-wheel-indicator {
                            border-color: rgb(75 85 99);
                            background: rgba(55 65 81 / 0.5);
                        }

                        /* Fade masks for top and bottom */
                        .time-wheel-container::before,
                        .time-wheel-container::after {
                            content: "";
                            position: absolute;
                            left: 0;
                            right: 0;
                            height: 40px;
                            pointer-events: none;
                            z-index: 2;
                        }
                        .time-wheel-container::before {
                            top: 0;
                            background: linear-gradient(to bottom, rgb(255 255 255) 0%, transparent 100%);
                        }
                        .time-wheel-container::after {
                            bottom: 0;
                            background: linear-gradient(to top, rgb(255 255 255) 0%, transparent 100%);
                        }
                        .dark .time-wheel-container::before {
                            background: linear-gradient(to bottom, rgb(31 41 55) 0%, transparent 100%);
                        }
                        .dark .time-wheel-container::after {
                            background: linear-gradient(to top, rgb(31 41 55) 0%, transparent 100%);
                        }
                    </style>
                    <script src="{{ asset(\'js/filament/time-picker.js\') }}"></script>
                ')
            );

        // Discover resources from enabled modules
        $this->discoverModuleResources($panel);

        return $panel;
    }

    private function getBrandLogo(string $key): ?string
    {
        try {
            $settingsService = app(SettingsServiceInterface::class);
            $logoPath = (string) $settingsService->get($key, '');

            if ($logoPath === '') {
                return null;
            }

            return Storage::disk('images')->url($logoPath);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Get the favicon URL for Filament.
     *
     * Uses custom light favicon if configured, otherwise falls back to static default.
     */
    private function getFavicon(): string
    {
        try {
            $settingsService = app(SettingsServiceInterface::class);
            $faviconPath = (string) $settingsService->get('site_favicon_light', '');

            if ($faviconPath !== '') {
                return Storage::disk('images')->url($faviconPath);
            }
        } catch (Throwable) {
            // Fall through to default
        }

        return '/favicons/light/favicon.ico';
    }

    /**
     * Get the primary color for Filament from settings.
     *
     * @return array{50: string, 100: string, 200: string, 300: string, 400: string, 500: string, 600: string, 700: string, 800: string, 900: string, 950: string}
     */
    private function getPrimaryColor(): array
    {
        try {
            $settingsService = app(SettingsServiceInterface::class);
            /** @var string $hexColor */
            $hexColor = $settingsService->get('theme_primary_color', '#D97706');

            return Color::hex($hexColor);
        } catch (Throwable) {
            // Fallback to amber if settings are unavailable
            return Color::Amber;
        }
    }

    /**
     * Get all navigation groups (core + modules).
     *
     * @return array<NavigationGroup>
     */
    private function getNavigationGroups(): array
    {
        // Core navigation groups with sort order (use translations)
        $coreGroups = [
            __('filament.navigation.content') => ['sort' => 10],
            __('filament.navigation.pages') => ['sort' => 30],
            __('filament.navigation.settings') => ['sort' => 40],
            __('filament.navigation.admin') => ['sort' => 50],
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
        try {
            $groups = [];
            $modulesPath = config('modules.path', base_path('modules'));

            if (! is_dir($modulesPath)) {
                return $groups;
            }

            // Get list of enabled module names
            $enabledModules = $this->getEnabledModuleNames();

            // If no modules enabled, skip the rest
            if ($enabledModules === []) {
                return $groups;
            }

            // Scan module directories for service providers
            $moduleDirectories = glob($modulesPath.'/*', GLOB_ONLYDIR);
            if ($moduleDirectories === false) {
                return $groups;
            }

            foreach ($moduleDirectories as $modulePath) {
                $moduleName = basename($modulePath);

                // Skip disabled modules
                if (! in_array($moduleName, $enabledModules, true)) {
                    continue;
                }

                try {
                    $studlyName = str_replace('-', '', ucwords($moduleName, '-'));

                    // Find the service provider file
                    $providerFile = $modulePath.'/src/'.$studlyName.'ServiceProvider.php';
                    if (! file_exists($providerFile)) {
                        continue;
                    }

                    // Register autoloader and load the provider
                    $this->registerModuleAutoloader("Modules\\{$studlyName}", $modulePath.'/src');

                    $providerClass = "Modules\\{$studlyName}\\{$studlyName}ServiceProvider";

                    // Load the provider file
                    require_once $providerFile;

                    if (! class_exists($providerClass)) {
                        continue;
                    }

                    // Load module translations first
                    $langPath = $modulePath.'/lang';
                    if (is_dir($langPath)) {
                        app('translator')->addNamespace($moduleName, $langPath);
                    }

                    /** @var ModuleServiceProvider $provider */
                    $provider = new $providerClass(app());
                    $moduleGroups = $provider->registerNavigationGroups();

                    foreach ($moduleGroups as $label => $options) {
                        if (! isset($groups[$label])) {
                            $groups[$label] = $options;
                        }
                    }
                } catch (Throwable $e) {
                    // Log the error but continue with other modules
                    logger()->warning("[AdminPanelProvider] Failed to load navigation groups for module: {$moduleName}", [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return $groups;
        } catch (Throwable $e) {
            // If anything fails, return empty groups and let core groups handle navigation
            logger()->error('[AdminPanelProvider] Failed to collect module navigation groups', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
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
            // Check if database is available
            if (! $this->isDatabaseAvailable()) {
                return $this->enabledModuleNamesCache = [];
            }

            if (! app()->bound(ModuleRepositoryInterface::class)) {
                return $this->enabledModuleNamesCache = [];
            }

            $repository = app(ModuleRepositoryInterface::class);
            $enabledModules = $repository->enabled()->all();

            $names = array_map(
                static fn ($module) => $module->name()->value,
                $enabledModules
            );

            return $this->enabledModuleNamesCache = $names;
        } catch (Throwable) {
            return $this->enabledModuleNamesCache = [];
        }
    }

    /**
     * Check if the database connection is available.
     */
    private function isDatabaseAvailable(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (Throwable) {
            return false;
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
        $prefix = rtrim($namespace, '\\').'\\';
        $prefixLength = strlen($prefix);

        spl_autoload_register(static function (string $class) use ($prefix, $prefixLength, $path): void {
            if (! str_starts_with($class, $prefix)) {
                return;
            }

            $file = $path.'/'.str_replace('\\', '/', substr($class, $prefixLength)).'.php';

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
        try {
            $modulesPath = config('modules.path', base_path('modules'));

            if (! is_dir($modulesPath)) {
                return;
            }

            // Get list of enabled module names
            $enabledModules = $this->getEnabledModuleNames();

            // If no modules enabled, skip resource discovery
            if ($enabledModules === []) {
                return;
            }

            $resources = [];

            $resourcesPaths = glob($modulesPath.'/*/src/Filament/Resources');
            if ($resourcesPaths === false) {
                return;
            }

            foreach ($resourcesPaths as $resourcesPath) {
                if (! is_dir($resourcesPath)) {
                    continue;
                }

                // Extract module name from path: modules/{module-name}/src/Filament/Resources
                $modulePath = dirname($resourcesPath, 3);
                $moduleName = basename($modulePath);

                // Skip disabled modules
                if (! in_array($moduleName, $enabledModules, true)) {
                    continue;
                }

                try {
                    $studlyName = str_replace('-', '', ucwords($moduleName, '-'));
                    $namespace = "Modules\\{$studlyName}\\Filament\\Resources";

                    // Register SPL autoloader for this module
                    $this->registerModuleAutoloader("Modules\\{$studlyName}", $modulePath.'/src');

                    // Find all resource files
                    $resourceFiles = glob($resourcesPath.'/*Resource.php');
                    if ($resourceFiles === false) {
                        continue;
                    }

                    foreach ($resourceFiles as $file) {
                        $className = basename($file, '.php');
                        $resourceClass = $namespace.'\\'.$className;

                        // Load the class file
                        require_once $file;

                        if (class_exists($resourceClass)) {
                            $resources[] = $resourceClass;
                        }
                    }
                } catch (Throwable $e) {
                    // Log the error but continue with other modules
                    logger()->warning("[AdminPanelProvider] Failed to discover resources for module: {$moduleName}", [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if ($resources !== []) {
                $panel->resources($resources);
            }
        } catch (Throwable $e) {
            // If resource discovery fails completely, log and continue without module resources
            logger()->error('[AdminPanelProvider] Failed to discover module resources', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Discover and collect Filament widgets from enabled modules.
     *
     * @return array<class-string<\Filament\Widgets\Widget>>
     */
    private function discoverModuleWidgets(): array
    {
        try {
            $modulesPath = config('modules.path', base_path('modules'));

            if (! is_dir($modulesPath)) {
                return [];
            }

            // Get list of enabled module names
            $enabledModules = $this->getEnabledModuleNames();

            // If no modules enabled, skip widget discovery
            if ($enabledModules === []) {
                return [];
            }

            $widgets = [];

            $moduleDirectories = glob($modulesPath.'/*', GLOB_ONLYDIR);
            if ($moduleDirectories === false) {
                return [];
            }

            foreach ($moduleDirectories as $modulePath) {
                $moduleName = basename($modulePath);

                // Skip disabled modules
                if (! in_array($moduleName, $enabledModules, true)) {
                    continue;
                }

                try {
                    $studlyName = str_replace('-', '', ucwords($moduleName, '-'));

                    // Find the service provider file
                    $providerFile = $modulePath.'/src/'.$studlyName.'ServiceProvider.php';
                    if (! file_exists($providerFile)) {
                        continue;
                    }

                    // Register autoloader and load the provider
                    $this->registerModuleAutoloader("Modules\\{$studlyName}", $modulePath.'/src');

                    $providerClass = "Modules\\{$studlyName}\\{$studlyName}ServiceProvider";

                    // Load the provider file
                    require_once $providerFile;

                    if (! class_exists($providerClass)) {
                        continue;
                    }

                    /** @var ModuleServiceProvider $provider */
                    $provider = new $providerClass(app());

                    // Check if the provider has registerFilamentWidgets method
                    if (method_exists($provider, 'registerFilamentWidgets')) {
                        $moduleWidgets = $provider->registerFilamentWidgets();
                        $widgets = array_merge($widgets, $moduleWidgets);
                    }
                } catch (Throwable $e) {
                    // Log the error but continue with other modules
                    logger()->warning("[AdminPanelProvider] Failed to discover widgets for module: {$moduleName}", [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return $widgets;
        } catch (Throwable $e) {
            // If widget discovery fails completely, log and continue without module widgets
            logger()->error('[AdminPanelProvider] Failed to discover module widgets', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Discover and collect Filament pages from enabled modules.
     *
     * @return array<class-string<\Filament\Pages\Page>>
     */
    private function discoverModulePages(): array
    {
        try {
            $modulesPath = config('modules.path', base_path('modules'));

            if (! is_dir($modulesPath)) {
                return [];
            }

            // Get list of enabled module names
            $enabledModules = $this->getEnabledModuleNames();

            // If no modules enabled, skip page discovery
            if ($enabledModules === []) {
                return [];
            }

            $pages = [];

            $moduleDirectories = glob($modulesPath.'/*', GLOB_ONLYDIR);
            if ($moduleDirectories === false) {
                return [];
            }

            foreach ($moduleDirectories as $modulePath) {
                $moduleName = basename($modulePath);

                // Skip disabled modules
                if (! in_array($moduleName, $enabledModules, true)) {
                    continue;
                }

                try {
                    $studlyName = str_replace('-', '', ucwords($moduleName, '-'));

                    // Find the service provider file
                    $providerFile = $modulePath.'/src/'.$studlyName.'ServiceProvider.php';
                    if (! file_exists($providerFile)) {
                        continue;
                    }

                    // Register autoloader and load the provider
                    $this->registerModuleAutoloader("Modules\\{$studlyName}", $modulePath.'/src');

                    $providerClass = "Modules\\{$studlyName}\\{$studlyName}ServiceProvider";

                    // Load the provider file
                    require_once $providerFile;

                    if (! class_exists($providerClass)) {
                        continue;
                    }

                    /** @var ModuleServiceProvider $provider */
                    $provider = new $providerClass(app());

                    $modulePages = $provider->registerFilamentPages();
                    $pages = array_merge($pages, $modulePages);
                } catch (Throwable $e) {
                    // Log the error but continue with other modules
                    logger()->warning("[AdminPanelProvider] Failed to discover pages for module: {$moduleName}", [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return $pages;
        } catch (Throwable $e) {
            // If page discovery fails completely, log and continue without module pages
            logger()->error('[AdminPanelProvider] Failed to discover module pages', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
