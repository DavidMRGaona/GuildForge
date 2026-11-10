<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Media\Services\MediaCatalogInterface;
use App\Application\Media\Services\MediaFileIdResolverInterface;
use App\Application\Media\Services\MediaSourceRegistryInterface;
use App\Infrastructure\Media\Cloudinary\CloudinaryMediaCatalog;
use App\Infrastructure\Media\Services\CloudinaryMediaFileIdResolver;
use App\Infrastructure\Media\Services\MediaSourceRegistry;
use App\Infrastructure\Media\Sources\ArticleMediaSource;
use App\Infrastructure\Media\Sources\AvatarMediaSource;
use App\Infrastructure\Media\Sources\EventMediaSource;
use App\Infrastructure\Media\Sources\GalleryPhotoMediaSource;
use App\Infrastructure\Media\Sources\HeroSlideMediaSource;
use App\Infrastructure\Media\Sources\SiteSettingsMediaSource;
use Cloudinary\Cloudinary;
use Illuminate\Support\ServiceProvider;

/**
 * Panel media library: the sources that say which records use each stored file.
 */
final class MediaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MediaSourceRegistryInterface::class, MediaSourceRegistry::class);
        $this->app->singleton(
            MediaFileIdResolverInterface::class,
            static fn (): MediaFileIdResolverInterface => new CloudinaryMediaFileIdResolver(
                is_string($prefix = config('filesystems.disks.images.prefix')) ? $prefix : null,
            ),
        );
        $this->app->singleton(
            MediaCatalogInterface::class,
            fn ($app): MediaCatalogInterface => new CloudinaryMediaCatalog(
                new Cloudinary(config('filesystems.disks.images.url') ?? config('cloudinary.cloud_url')),
                $app->make(MediaFileIdResolverInterface::class),
            ),
        );
    }

    public function boot(): void
    {
        $this->app->make(MediaSourceRegistryInterface::class)->registerMany([
            EventMediaSource::class,
            ArticleMediaSource::class,
            GalleryPhotoMediaSource::class,
            HeroSlideMediaSource::class,
            SiteSettingsMediaSource::class,
            AvatarMediaSource::class,
        ]);
    }
}
