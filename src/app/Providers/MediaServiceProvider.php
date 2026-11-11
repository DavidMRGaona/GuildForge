<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Media\Services\MediaCatalogInterface;
use App\Application\Media\Services\MediaDeletionServiceInterface;
use App\Application\Media\Services\MediaFileIdResolverInterface;
use App\Application\Media\Services\MediaFileReleaserInterface;
use App\Application\Media\Services\MediaIndexerInterface;
use App\Application\Media\Services\MediaSourceRegistryInterface;
use App\Application\Media\Services\MediaUrlExtractorInterface;
use App\Domain\Knowledge\Enums\GuideSource;
use App\Domain\Modules\Events\ModuleDisabled;
use App\Domain\Modules\Events\ModuleEnabled;
use App\Domain\Modules\Events\ModuleUninstalled;
use App\Domain\Modules\Events\ModuleUpdated;
use App\Domain\Updates\Events\ModuleUpdateCompleted;
use App\Filament\Media\Sources\ArticleMediaSource;
use App\Filament\Media\Sources\EventMediaSource;
use App\Filament\Media\Sources\GalleryPhotoMediaSource;
use App\Filament\Media\Sources\HeroSlideMediaSource;
use App\Filament\Media\Sources\RichTextMediaSource;
use App\Filament\Media\Sources\SiteSettingsMediaSource;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeGuideModel;
use App\Infrastructure\Media\Cloudinary\CloudinaryMediaCatalog;
use App\Infrastructure\Media\Cloudinary\DeferredMediaCatalog;
use App\Infrastructure\Media\Listeners\SyncMediaLibraryOnModuleChange;
use App\Infrastructure\Media\Observers\RefreshMediaUsagesObserver;
use App\Infrastructure\Media\Services\CloudinaryMediaFileIdResolver;
use App\Infrastructure\Media\Services\EloquentMediaLibraryQuery;
use App\Infrastructure\Media\Services\MediaDeletionService;
use App\Infrastructure\Media\Services\MediaFileReleaser;
use App\Infrastructure\Media\Services\MediaIndexer;
use App\Infrastructure\Media\Services\MediaLibraryQueryInterface;
use App\Infrastructure\Media\Services\MediaSourceRegistry;
use App\Infrastructure\Media\Services\MediaUrlExtractor;
use App\Infrastructure\Media\Sources\AvatarMediaSource;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\HeroSlideModel;
use App\Infrastructure\Persistence\Eloquent\Models\PhotoModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Cloudinary\Cloudinary;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
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
        // The files a stored text names, for every source that reads rich text (core and modules)
        $this->app->bind(MediaUrlExtractorInterface::class, MediaUrlExtractor::class);
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
        // Not a singleton either: the catalog and the switch are read when it deletes, never cached
        $this->app->bind(MediaDeletionServiceInterface::class, MediaDeletionService::class);
        // What a record calls when it lets go of a file: deleted later, only if nobody uses it
        $this->app->bind(MediaFileReleaserInterface::class, MediaFileReleaser::class);
        // What the panel library lists; it reads the sources and the clock on every call
        $this->app->bind(MediaLibraryQueryInterface::class, EloquentMediaLibraryQuery::class);
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
            RichTextMediaSource::class,
        ]);

        // Observer instances carry their arguments, so they are attached as model event listeners
        $own = static fn (Model $model): array => [(string) $model->getKey()];
        $this->refreshOnChange(EventModel::class, new RefreshMediaUsagesObserver('events', $own));
        $this->refreshOnChange(ArticleModel::class, new RefreshMediaUsagesObserver('articles', $own));
        $this->refreshOnChange(HeroSlideModel::class, new RefreshMediaUsagesObserver('hero_slides', $own));
        // The texts of the same records feed the rich text source, under ids prefixed with their kind
        $this->refreshOnChange(EventModel::class, new RefreshMediaUsagesObserver(RichTextMediaSource::TYPE, static fn (Model $event): array => ['events:'.$event->getKey()]));
        $this->refreshOnChange(ArticleModel::class, new RefreshMediaUsagesObserver(RichTextMediaSource::TYPE, static fn (Model $article): array => ['articles:'.$article->getKey()]));
        // Only the site's own guides are read; the catalog sync writes official ones in bulk
        $this->refreshOnChange(KnowledgeGuideModel::class, new RefreshMediaUsagesObserver(
            RichTextMediaSource::TYPE,
            static fn (Model $guide): array => $guide->getAttribute('source') === GuideSource::Site->value ? ['guides:'.$guide->getKey()] : [],
        ));
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

        // A module's sources come and go with it: the library syncs instead of waiting for the night (core#517)
        foreach ([ModuleEnabled::class, ModuleDisabled::class, ModuleUpdated::class, ModuleUninstalled::class, ModuleUpdateCompleted::class] as $event) {
            Event::listen($event, SyncMediaLibraryOnModuleChange::class);
        }
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
