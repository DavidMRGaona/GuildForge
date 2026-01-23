<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Factories\ResponseDTOFactoryInterface;
use App\Application\Services\ArticleQueryServiceInterface;
use App\Application\Services\AuthServiceInterface;
use App\Application\Services\EventQueryServiceInterface;
use App\Application\Services\GalleryQueryServiceInterface;
use App\Application\Services\HeroSlideQueryServiceInterface;
use App\Application\Services\AboutPageServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Application\Services\SitemapQueryServiceInterface;
use App\Application\Services\ImageOptimizationServiceInterface;
use App\Application\Services\TagQueryServiceInterface;
use App\Application\Services\ThemeSettingsServiceInterface;
use App\Application\Modules\Services\ModuleContextServiceInterface;
use App\Application\Modules\Services\ModuleManagerServiceInterface;
use App\Application\Modules\Services\ModuleNavigationRegistryInterface;
use App\Application\Modules\Services\ModulePermissionRegistryInterface;
use App\Application\Modules\Services\ModuleScaffoldingServiceInterface;
use App\Application\Modules\Services\ModuleSlotRegistryInterface;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use App\Infrastructure\Modules\Services\ModuleDependencyResolver;
use App\Infrastructure\Modules\Services\ModuleDiscoveryService;
use App\Infrastructure\Modules\Services\ModuleManagerService;
use App\Infrastructure\Modules\Services\ModuleContextService;
use App\Infrastructure\Modules\Services\ModuleMigrationRunner;
use App\Infrastructure\Modules\Services\ModuleNavigationRegistry;
use App\Infrastructure\Modules\Services\ModulePermissionRegistry;
use App\Infrastructure\Modules\Services\ModuleScaffoldingService;
use App\Infrastructure\Modules\Services\ModuleSlotRegistry;
use App\Infrastructure\Modules\Services\StubRenderer;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentModuleRepository;
use App\Domain\Repositories\ArticleRepositoryInterface;
use App\Domain\Repositories\EventRepositoryInterface;
use App\Domain\Repositories\GalleryRepositoryInterface;
use App\Domain\Repositories\PhotoRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentArticleRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentEventRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentGalleryRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentPhotoRepository;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\GalleryModel;
use App\Infrastructure\Persistence\Eloquent\Models\HeroSlideModel;
use App\Infrastructure\Persistence\Eloquent\Models\TagModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Services\ArticleQueryService;
use App\Infrastructure\Services\AuthService;
use App\Infrastructure\Factories\EloquentResponseDTOFactory;
use App\Infrastructure\Services\CloudinaryStorageAdapter;
use App\Infrastructure\Services\EventQueryService;
use App\Infrastructure\Services\GalleryQueryService;
use App\Infrastructure\Services\HeroSlideQueryService;
use App\Infrastructure\Services\SettingsService;
use App\Infrastructure\Services\SitemapQueryService;
use App\Infrastructure\Services\AboutPageService;
use App\Infrastructure\Services\ImageOptimizationService;
use App\Infrastructure\Services\TagQueryService;
use App\Infrastructure\Services\ThemeSettingsService;
use Cloudinary\Cloudinary;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem;
use App\Policies\ArticlePolicy;
use App\Policies\EventPolicy;
use App\Policies\GalleryPolicy;
use App\Policies\HeroSlidePolicy;
use App\Policies\TagPolicy;
use App\Policies\UserPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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

        // Application Service bindings
        $this->app->singleton(SettingsServiceInterface::class, SettingsService::class);
        $this->app->singleton(ThemeSettingsServiceInterface::class, ThemeSettingsService::class);
        $this->app->singleton(ImageOptimizationServiceInterface::class, ImageOptimizationService::class);
        $this->app->singleton(AuthServiceInterface::class, AuthService::class);

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
        // Rate limiter for contact form: 3 submissions per minute per IP
        RateLimiter::for(
            'contact',
            static fn (Request $request) => Limit::perMinute(3)->by($request->ip() ?? 'unknown')
        );

        Gate::policy(UserModel::class, UserPolicy::class);
        Gate::policy(EventModel::class, EventPolicy::class);
        Gate::policy(ArticleModel::class, ArticlePolicy::class);
        Gate::policy(GalleryModel::class, GalleryPolicy::class);
        Gate::policy(HeroSlideModel::class, HeroSlidePolicy::class);
        Gate::policy(TagModel::class, TagPolicy::class);

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
