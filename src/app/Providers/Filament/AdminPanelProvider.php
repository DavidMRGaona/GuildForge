<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Application\Services\SettingsServiceInterface;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panel = $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName(config('app.name') . ' Admin')
            ->brandLogo(fn (): ?string => $this->getBrandLogo('site_logo_light'))
            ->darkModeBrandLogo(fn (): ?string => $this->getBrandLogo('site_logo_dark'))
            ->brandLogoHeight('2.5rem')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->darkMode(true)
            ->navigationGroups([
                NavigationGroup::make('Contenido'),
                NavigationGroup::make('Páginas'),
                NavigationGroup::make('Configuración'),
                NavigationGroup::make('Administración'),
            ])
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

    /**
     * Discover and register Filament resources from enabled modules.
     */
    private function discoverModuleResources(Panel $panel): void
    {
        $modulesPath = config('modules.path', base_path('modules'));

        if (!is_dir($modulesPath)) {
            return;
        }

        $resources = [];

        foreach (glob($modulesPath . '/*/src/Filament/Resources') as $resourcesPath) {
            if (!is_dir($resourcesPath)) {
                continue;
            }

            // Extract module name from path: modules/{module-name}/src/Filament/Resources
            $modulePath = dirname(dirname(dirname($resourcesPath)));
            $moduleName = basename($modulePath);
            $studlyName = str_replace('-', '', ucwords($moduleName, '-'));
            $namespace = "Modules\\{$studlyName}\\Filament\\Resources";

            // Register SPL autoloader for this module
            $this->registerModuleAutoloader("Modules\\{$studlyName}", $modulePath . '/src');

            // Find all resource files
            foreach (glob($resourcesPath . '/*Resource.php') as $file) {
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

    /**
     * Register SPL autoloader for a module namespace.
     */
    private function registerModuleAutoloader(string $namespace, string $path): void
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

    private function getBrandLogo(string $key): ?string
    {
        try {
            $settingsService = app(SettingsServiceInterface::class);
            $logoPath = (string) $settingsService->get($key, '');

            if ($logoPath === '') {
                return null;
            }

            return Storage::disk('images')->url($logoPath);
        } catch (\Throwable) {
            return null;
        }
    }
}
