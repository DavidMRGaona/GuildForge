<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Services\SettingsServiceInterface;
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
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Services\CloudinaryStorageAdapter;
use App\Infrastructure\Services\SettingsService;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem;
use App\Policies\ArticlePolicy;
use App\Policies\EventPolicy;
use App\Policies\GalleryPolicy;
use App\Policies\HeroSlidePolicy;
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
        $this->app->bind(ArticleRepositoryInterface::class, EloquentArticleRepository::class);
        $this->app->bind(EventRepositoryInterface::class, EloquentEventRepository::class);
        $this->app->bind(GalleryRepositoryInterface::class, EloquentGalleryRepository::class);
        $this->app->bind(PhotoRepositoryInterface::class, EloquentPhotoRepository::class);
        $this->app->singleton(SettingsServiceInterface::class, SettingsService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Rate limiter for contact form: 3 submissions per minute per IP
        RateLimiter::for(
            'contact',
            fn (Request $request) =>
            Limit::perMinute(3)->by($request->ip() ?? 'unknown')
        );

        Gate::policy(UserModel::class, UserPolicy::class);
        Gate::policy(EventModel::class, EventPolicy::class);
        Gate::policy(ArticleModel::class, ArticlePolicy::class);
        Gate::policy(GalleryModel::class, GalleryPolicy::class);
        Gate::policy(HeroSlideModel::class, HeroSlidePolicy::class);

        // Override cloudinary driver with safe adapter that:
        // - Generates URLs directly (no Admin API calls)
        // - Ignores "not found" errors on delete
        Storage::extend('cloudinary', function ($app, $config) {
            $cloudinaryUrl = $config['url'] ?? config('cloudinary.cloud_url');
            $prefix = $config['prefix'] ?? null;

            $cloudinary = new \Cloudinary\Cloudinary($cloudinaryUrl);
            $adapter = new CloudinaryStorageAdapter($cloudinary, null, $prefix);

            return new FilesystemAdapter(
                new Filesystem($adapter),
                $adapter,
                $config
            );
        });
    }
}
