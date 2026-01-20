<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Factories\ResponseDTOFactoryInterface;
use App\Application\Services\ArticleQueryServiceInterface;
use App\Application\Services\EventQueryServiceInterface;
use App\Application\Services\GalleryQueryServiceInterface;
use App\Application\Services\HeroSlideQueryServiceInterface;
use App\Application\Services\AboutPageServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Application\Services\SitemapQueryServiceInterface;
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
use App\Infrastructure\Factories\EloquentResponseDTOFactory;
use App\Infrastructure\Services\CloudinaryStorageAdapter;
use App\Infrastructure\Services\EventQueryService;
use App\Infrastructure\Services\GalleryQueryService;
use App\Infrastructure\Services\HeroSlideQueryService;
use App\Infrastructure\Services\SettingsService;
use App\Infrastructure\Services\SitemapQueryService;
use App\Infrastructure\Services\AboutPageService;
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

        // Factory bindings
        $this->app->singleton(ResponseDTOFactoryInterface::class, EloquentResponseDTOFactory::class);

        // Query Service bindings (singletons for performance)
        $this->app->singleton(EventQueryServiceInterface::class, EventQueryService::class);
        $this->app->singleton(ArticleQueryServiceInterface::class, ArticleQueryService::class);
        $this->app->singleton(GalleryQueryServiceInterface::class, GalleryQueryService::class);
        $this->app->singleton(HeroSlideQueryServiceInterface::class, HeroSlideQueryService::class);
        $this->app->singleton(SitemapQueryServiceInterface::class, SitemapQueryService::class);
        $this->app->singleton(AboutPageServiceInterface::class, AboutPageService::class);
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
        Storage::extend('cloudinary', static function ($app, $config) {
            $cloudinaryUrl = $config['url'] ?? config('cloudinary.cloud_url');
            $prefix = $config['prefix'] ?? null;

            $cloudinary = new Cloudinary($cloudinaryUrl);
            $adapter = new CloudinaryStorageAdapter($cloudinary, null, $prefix);

            return new FilesystemAdapter(
                new Filesystem($adapter),
                $adapter,
                $config
            );
        });
    }
}
