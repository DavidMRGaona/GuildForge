<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Media\Services\MediaCatalogInterface;
use App\Application\Media\Services\MediaFileIdResolverInterface;
use App\Application\Media\Services\MediaIndexerInterface;
use App\Application\Media\Services\MediaSourceRegistryInterface;
use App\Filament\Media\Sources\ArticleMediaSource;
use App\Filament\Media\Sources\EventMediaSource;
use App\Filament\Media\Sources\GalleryPhotoMediaSource;
use App\Filament\Media\Sources\HeroSlideMediaSource;
use App\Filament\Media\Sources\SiteSettingsMediaSource;
use App\Infrastructure\Media\Cloudinary\CloudinaryMediaCatalog;
use App\Infrastructure\Media\Cloudinary\DeferredMediaCatalog;
use App\Infrastructure\Media\Observers\RefreshMediaUsagesObserver;
use App\Infrastructure\Media\Services\CloudinaryMediaFileIdResolver;
use App\Infrastructure\Media\Services\MediaIndexer;
use App\Infrastructure\Media\Services\MediaSourceRegistry;
use App\Infrastructure\Media\Sources\AvatarMediaSource;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\HeroSlideModel;
use App\Infrastructure\Persistence\Eloquent\Models\PhotoModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Cloudinary\Cloudinary;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

/**
 * Panel media library: the sources that say which records use each stored file, and the index kept in
 * line with them on every save and by the nightly media:sync.
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
        // The Cloudinary client is built on first use: without valid settings it throws, and that must only
        // make the catalog unavailable, never break whatever resolved it
        $this->app->singleton(
            MediaCatalogInterface::class,
            static fn ($app): MediaCatalogInterface => new DeferredMediaCatalog(
                static fn (): MediaCatalogInterface => new CloudinaryMediaCatalog(
                    new Cloudinary(config('filesystems.disks.images.url') ?? config('cloudinary.cloud_url')),
                    $app->make(MediaFileIdResolverInterface::class),
                ),
            ),
        );
        // Not a singleton: it takes the bus dispatcher at resolution time, so Bus::fake() reaches it
        $this->app->bind(MediaIndexerInterface::class, MediaIndexer::class);
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

        // Observer instances carry their arguments, so they are attached as model event listeners
        $own = static fn (Model $model): array => [(string) $model->getKey()];
        $this->refreshOnChange(EventModel::class, new RefreshMediaUsagesObserver('events', $own));
        $this->refreshOnChange(ArticleModel::class, new RefreshMediaUsagesObserver('articles', $own));
        $this->refreshOnChange(HeroSlideModel::class, new RefreshMediaUsagesObserver('hero_slides', $own));
        // Each gallery photo is its own record of the galleries source
        $this->refreshOnChange(PhotoModel::class, new RefreshMediaUsagesObserver('galleries', $own));
        // Accounts are saved on every sign-in: only a change to what the avatars source stores is refreshed
        $this->refreshOnChange(UserModel::class, new RefreshMediaUsagesObserver('avatars', static function (Model $user): array {
            $avatar = $user->getAttribute('avatar_public_id');
            $relevant = ! $user->exists
                || $user->wasChanged(['avatar_public_id', 'name'])
                || ($user->wasRecentlyCreated && is_string($avatar) && $avatar !== '');

            return $relevant ? [(string) $user->getKey()] : [];
        }));
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function refreshOnChange(string $modelClass, RefreshMediaUsagesObserver $observer): void
    {
        $modelClass::saved($observer->saved(...));
        $modelClass::deleted($observer->deleted(...));
    }
}
