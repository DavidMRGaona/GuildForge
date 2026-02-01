<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Factories\ResponseDTOFactoryInterface;
use App\Application\Modules\Services\ModuleContextServiceInterface;
use App\Application\Modules\Services\ModuleManagerServiceInterface;
use App\Application\Modules\Services\ModuleNavigationRegistryInterface;
use App\Application\Modules\Services\ModulePageRegistryInterface;
use App\Application\Modules\Services\ModulePermissionRegistryInterface;
use App\Application\Modules\Services\ModuleRouteRegistryInterface;
use App\Application\Modules\Services\ModuleScaffoldingServiceInterface;
use App\Application\Modules\Services\ModuleSlotRegistryInterface;
use App\Application\Navigation\Services\MenuItemHrefResolverInterface;
use App\Application\Navigation\Services\MenuServiceInterface;
use App\Application\Navigation\Services\RouteRegistryInterface;
use App\Application\Services\AboutPageServiceInterface;
use App\Application\Services\ArticleQueryServiceInterface;
use App\Application\Services\AuthServiceInterface;
use App\Application\Services\ContactServiceInterface;
use App\Application\Services\LegalPageServiceInterface;
use App\Application\Services\EventQueryServiceInterface;
use App\Application\Services\GalleryQueryServiceInterface;
use App\Application\Services\HeroSlideQueryServiceInterface;
use App\Application\Services\ImageOptimizationServiceInterface;
use App\Application\Services\LogContextProviderInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Application\Services\SitemapQueryServiceInterface;
use App\Application\Services\SlugRedirectService;
use App\Application\Services\SlugRedirectServiceInterface;
use App\Application\Services\TagQueryServiceInterface;
use App\Application\Services\ThemeSettingsServiceInterface;
use App\Application\Services\UserModelQueryServiceInterface;
use App\Application\Services\UserServiceInterface;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use App\Domain\Navigation\Repositories\MenuItemRepositoryInterface;
use App\Domain\Repositories\ArticleRepositoryInterface;
use App\Domain\Repositories\EventRepositoryInterface;
use App\Domain\Repositories\GalleryRepositoryInterface;
use App\Domain\Repositories\PhotoRepositoryInterface;
use App\Domain\Repositories\SlugRedirectRepositoryInterface;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Infrastructure\Auth\UuidEloquentUserProvider;
use App\Infrastructure\Factories\EloquentResponseDTOFactory;
use App\Infrastructure\Modules\Services\ModuleContextService;
use App\Infrastructure\Modules\Services\ModuleDependencyResolver;
use App\Infrastructure\Modules\Services\ModuleDiscoveryService;
use App\Infrastructure\Modules\Services\ModuleManagerService;
use App\Infrastructure\Modules\Services\ModuleMigrationRunner;
use App\Infrastructure\Modules\Services\ModuleNavigationRegistry;
use App\Infrastructure\Modules\Services\ModulePageRegistry;
use App\Infrastructure\Modules\Services\ModulePermissionRegistry;
use App\Infrastructure\Modules\Services\ModuleRouteRegistry;
use App\Infrastructure\Modules\Services\ModuleScaffoldingService;
use App\Infrastructure\Modules\Services\ModuleSeederRunner;
use App\Infrastructure\Modules\Services\ModuleSlotRegistry;
use App\Infrastructure\Modules\Services\StubRenderer;
use App\Infrastructure\Navigation\Persistence\Eloquent\Models\MenuItemModel;
use App\Infrastructure\Navigation\Persistence\Eloquent\Repositories\EloquentMenuItemRepository;
use App\Infrastructure\Navigation\Services\MenuItemHrefResolver;
use App\Infrastructure\Navigation\Services\MenuService;
use App\Infrastructure\Navigation\Services\RouteRegistry;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\GalleryModel;
use App\Infrastructure\Persistence\Eloquent\Models\HeroSlideModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\TagModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentArticleRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentEventRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentGalleryRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentModuleRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentPhotoRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentSlugRedirectRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentUserRepository;
use App\Infrastructure\Services\AboutPageService;
use App\Infrastructure\Services\ArticleQueryService;
use App\Infrastructure\Services\AuthService;
use App\Infrastructure\Services\ContactService;
use App\Infrastructure\Services\LegalPageService;
use App\Infrastructure\Services\CloudinaryStorageAdapter;
use App\Infrastructure\Services\EventQueryService;
use App\Infrastructure\Services\GalleryQueryService;
use App\Infrastructure\Services\HeroSlideQueryService;
use App\Infrastructure\Services\ImageOptimizationService;
use App\Infrastructure\Services\Logging\HttpLogContextProvider;
use App\Infrastructure\Services\SettingsService;
use App\Infrastructure\Services\SitemapQueryService;
use App\Infrastructure\Services\TagQueryService;
use App\Infrastructure\Services\ThemeSettingsService;
use App\Infrastructure\Services\UserModelQueryService;
use App\Infrastructure\Services\UserService;
use App\Policies\ArticlePolicy;
use App\Policies\EventPolicy;
use App\Policies\GalleryPolicy;
use App\Policies\HeroSlidePolicy;
use App\Policies\MenuItemPolicy;
use App\Policies\RolePolicy;
use App\Policies\TagPolicy;
use App\Policies\UserPolicy;
use Cloudinary\Cloudinary;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Domain Repository bindings
        $this->app->bind(ArticleRepositoryInterface::class, EloquentArticleRepository::class);
        $this->app->bind(EventRepositoryInterface::class, EloquentEventRepository::class);
        $this->app->bind(GalleryRepositoryInterface::class, EloquentGalleryRepository::class);
        $this->app->bind(PhotoRepositoryInterface::class, EloquentPhotoRepository::class);
        $this->app->bind(UserRepositoryInterface::class, EloquentUserRepository::class);
        $this->app->bind(SlugRedirectRepositoryInterface::class, EloquentSlugRedirectRepository::class);

        // Slug redirect system
        $this->app->singleton(SlugRedirectServiceInterface::class, SlugRedirectService::class);

        // Application Service bindings
        $this->app->singleton(SettingsServiceInterface::class, SettingsService::class);
        $this->app->singleton(ThemeSettingsServiceInterface::class, ThemeSettingsService::class);
        $this->app->singleton(ImageOptimizationServiceInterface::class, ImageOptimizationService::class);
        $this->app->singleton(AuthServiceInterface::class, AuthService::class);
        $this->app->singleton(UserModelQueryServiceInterface::class, UserModelQueryService::class);
        $this->app->singleton(UserServiceInterface::class, UserService::class);
        $this->app->singleton(LegalPageServiceInterface::class, LegalPageService::class);
        $this->app->singleton(ContactServiceInterface::class, ContactService::class);

        // Log context provider (uses request scoped binding)
        $this->app->bind(LogContextProviderInterface::class, function ($app) {
            return new HttpLogContextProvider($app['request'] ?? null);
        });

        // Factory bindings
        $this->app->singleton(ResponseDTOFactoryInterface::class, EloquentResponseDTOFactory::class);

        // Query Service bindings (singletons for performance)
        $this->app->singleton(EventQueryServiceInterface::class, EventQueryService::class);
        $this->app->singleton(ArticleQueryServiceInterface::class, ArticleQueryService::class);
        $this->app->singleton(GalleryQueryServiceInterface::class, GalleryQueryService::class);
        $this->app->singleton(HeroSlideQueryServiceInterface::class, HeroSlideQueryService::class);
        $this->app->singleton(SitemapQueryServiceInterface::class, SitemapQueryService::class);
        $this->app->singleton(TagQueryServiceInterface::class, TagQueryService::class);
        $this->app->singleton(AboutPageServiceInterface::class, AboutPageService::class);

        // Module system bindings
        $this->app->bind(ModuleRepositoryInterface::class, EloquentModuleRepository::class);

        $this->app->singleton(ModuleDiscoveryService::class, function ($app) {
            return new ModuleDiscoveryService(
                modulesPath: config('modules.path'),
            );
        });

        $this->app->singleton(ModuleDependencyResolver::class, function () {
            return new ModuleDependencyResolver();
        });

        $this->app->singleton(ModuleMigrationRunner::class, function ($app) {
            return new ModuleMigrationRunner(
                modulesPath: config('modules.path'),
            );
        });

        $this->app->singleton(ModuleSeederRunner::class, function ($app) {
            return new ModuleSeederRunner(
                modulesPath: config('modules.path'),
            );
        });

        $this->app->singleton(ModuleManagerServiceInterface::class, ModuleManagerService::class);

        // Module SDK services
        $this->app->singleton(StubRenderer::class, function () {
            return new StubRenderer(base_path('stubs/modules'));
        });

        $this->app->singleton(ModuleScaffoldingServiceInterface::class, function ($app) {
            return new ModuleScaffoldingService(
                $app->make(StubRenderer::class),
                config('modules.path'),
            );
        });

        $this->app->singleton(ModuleContextServiceInterface::class, ModuleContextService::class);
        $this->app->singleton(ModulePermissionRegistryInterface::class, ModulePermissionRegistry::class);
        $this->app->singleton(ModuleNavigationRegistryInterface::class, ModuleNavigationRegistry::class);
        $this->app->singleton(ModuleSlotRegistryInterface::class, ModuleSlotRegistry::class);
        $this->app->singleton(ModulePageRegistryInterface::class, ModulePageRegistry::class);
        $this->app->singleton(ModuleRouteRegistryInterface::class, ModuleRouteRegistry::class);

        // Navigation system bindings
        $this->app->bind(MenuItemRepositoryInterface::class, EloquentMenuItemRepository::class);
        $this->app->singleton(MenuItemHrefResolverInterface::class, MenuItemHrefResolver::class);
        $this->app->singleton(MenuServiceInterface::class, MenuService::class);
        $this->app->singleton(RouteRegistryInterface::class, RouteRegistry::class);

        // Module loader (for booting enabled modules)
        $this->app->singleton(\App\Modules\ModuleLoader::class, function ($app) {
            return new \App\Modules\ModuleLoader(
                $app,
                $app->make(ModuleRepositoryInterface::class),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register custom UUID-aware user provider to handle old integer-based cookies gracefully
        Auth::provider('uuid-eloquent', function ($app, array $config) {
            return new UuidEloquentUserProvider($app['hash'], $config['model']);
        });

        // Rate limiter for contact form: 3 submissions per minute per IP
        RateLimiter::for(
            'contact',
            static fn (Request $request) => Limit::perMinute(3)->by($request->ip() ?? 'unknown')
        );

        Gate::policy(UserModel::class, UserPolicy::class);
        Gate::policy(RoleModel::class, RolePolicy::class);
        Gate::policy(EventModel::class, EventPolicy::class);
        Gate::policy(ArticleModel::class, ArticlePolicy::class);
        Gate::policy(GalleryModel::class, GalleryPolicy::class);
        Gate::policy(HeroSlideModel::class, HeroSlidePolicy::class);
        Gate::policy(TagModel::class, TagPolicy::class);
        Gate::policy(MenuItemModel::class, MenuItemPolicy::class);

        Event::listen(
            \App\Domain\Modules\Events\ModuleDisabled::class,
            \App\Infrastructure\Navigation\Listeners\DeactivateMenuItemsOnModuleDisabled::class,
        );
        Event::listen(
            \App\Domain\Modules\Events\ModuleEnabled::class,
            \App\Infrastructure\Navigation\Listeners\ActivateMenuItemsOnModuleEnabled::class,
        );

        // Clear Laravel caches when modules change (required for cached routes in production)
        Event::listen(
            \App\Domain\Modules\Events\ModuleEnabled::class,
            \App\Infrastructure\Modules\Listeners\ClearCachesOnModuleChange::class,
        );
        Event::listen(
            \App\Domain\Modules\Events\ModuleDisabled::class,
            \App\Infrastructure\Modules\Listeners\ClearCachesOnModuleChange::class,
        );
        Event::listen(
            \App\Domain\Modules\Events\ModuleUninstalled::class,
            \App\Infrastructure\Navigation\Listeners\DeleteMenuItemsOnModuleUninstalled::class,
        );

        // Override cloudinary driver with a safe adapter that:
        // - Generates URLs directly (no Admin API calls)
        // - Ignores "not found" errors on delete
        // - Optimizes images before upload (resize/compress)
        Storage::extend('cloudinary', static function ($app, $config) {
            $cloudinaryUrl = $config['url'] ?? config('cloudinary.cloud_url');
            $prefix = $config['prefix'] ?? null;

            $cloudinary = new Cloudinary($cloudinaryUrl);
            $imageOptimization = $app->make(ImageOptimizationServiceInterface::class);
            $adapter = new CloudinaryStorageAdapter($cloudinary, null, $prefix, $imageOptimization);

            return new FilesystemAdapter(
                new Filesystem($adapter),
                $adapter,
                $config
            );
        });
    }
}
