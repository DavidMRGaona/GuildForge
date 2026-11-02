<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Search\Services\SearchDocumentStoreInterface;
use App\Application\Search\Services\SearchIndexerInterface;
use App\Application\Search\Services\SearchSourceRegistryInterface;
use App\Application\Search\Services\SearchSyncRunnerInterface;
use App\Domain\Modules\Events\ModuleDisabled;
use App\Domain\Modules\Events\ModuleEnabled;
use App\Domain\Modules\Events\ModuleUninstalled;
use App\Domain\Modules\Events\ModuleUpdated;
use App\Domain\Updates\Events\ModuleUpdateCompleted;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\GalleryModel;
use App\Infrastructure\Persistence\Eloquent\Models\PhotoModel;
use App\Infrastructure\Search\Index\NullSearchIndexWriter;
use App\Infrastructure\Search\Index\ScoutSearchIndexWriter;
use App\Infrastructure\Search\Index\SearchIndexWriterInterface;
use App\Infrastructure\Search\Listeners\SyncSearchOnModuleChange;
use App\Infrastructure\Search\Observers\RefreshSearchDocumentObserver;
use App\Infrastructure\Search\Persistence\Eloquent\Models\SearchDocumentModel;
use App\Infrastructure\Search\Persistence\SearchDocumentStore;
use App\Infrastructure\Search\Services\ProcessSearchSyncRunner;
use App\Infrastructure\Search\Services\SearchIndexer;
use App\Infrastructure\Search\Sources\ArticleSearchSource;
use App\Infrastructure\Search\Sources\EventSearchSource;
use App\Infrastructure\Search\Sources\GallerySearchSource;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class SearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SearchDocumentStoreInterface::class, SearchDocumentStore::class);
        $this->app->bind(SearchIndexerInterface::class, SearchIndexer::class);
        $this->app->bind(SearchSyncRunnerInterface::class, ProcessSearchSyncRunner::class);

        // Meilisearch when Scout is configured for it, otherwise nothing: the database search reads the table
        $this->app->bind(SearchIndexWriterInterface::class, fn (Application $app): SearchIndexWriterInterface => $app->make('config')->get('scout.driver') === 'meilisearch'
            ? $app->make(ScoutSearchIndexWriter::class)
            : $app->make(NullSearchIndexWriter::class));
    }

    public function boot(): void
    {
        // Only the indexer writes to the search index: saving a document never pushes it on its own
        SearchDocumentModel::disableSearchSyncing();

        $this->app->make(SearchSourceRegistryInterface::class)->registerMany([
            EventSearchSource::class,
            ArticleSearchSource::class,
            GallerySearchSource::class,
        ]);

        // Observer instances carry their arguments, so they are attached as model event listeners
        $own = static fn (Model $model): array => [(string) $model->getKey()];
        $this->refreshOnChange(EventModel::class, new RefreshSearchDocumentObserver('events', $own));
        $this->refreshOnChange(ArticleModel::class, new RefreshSearchDocumentObserver('articles', $own));
        $this->refreshOnChange(GalleryModel::class, new RefreshSearchDocumentObserver('galleries', $own));
        // A photo is part of its gallery's document: it refreshes the gallery and never forgets it
        $this->refreshOnChange(PhotoModel::class, new RefreshSearchDocumentObserver('galleries', static function (Model $photo): array {
            $ids = [(string) $photo->getAttribute('gallery_id')];

            // A photo moved to another gallery leaves its caption in the old gallery's document
            if ($photo->wasChanged('gallery_id') && $photo->getOriginal('gallery_id') !== null) {
                $ids[] = (string) $photo->getOriginal('gallery_id');
            }

            return $ids;
        }, forgetOnDelete: false));

        foreach ([ModuleEnabled::class, ModuleDisabled::class, ModuleUpdated::class, ModuleUninstalled::class, ModuleUpdateCompleted::class] as $event) {
            Event::listen($event, SyncSearchOnModuleChange::class);
        }
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function refreshOnChange(string $modelClass, RefreshSearchDocumentObserver $observer): void
    {
        $modelClass::saved($observer->saved(...));
        $modelClass::deleted($observer->deleted(...));
    }
}
