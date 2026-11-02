<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Search\Services\SearchDocumentStoreInterface;
use App\Application\Search\Services\SearchIndexerInterface;
use App\Domain\Modules\Events\ModuleDisabled;
use App\Domain\Modules\Events\ModuleEnabled;
use App\Domain\Modules\Events\ModuleUninstalled;
use App\Domain\Modules\Events\ModuleUpdated;
use App\Domain\Updates\Events\ModuleUpdateCompleted;
use App\Infrastructure\Search\Index\NullSearchIndexWriter;
use App\Infrastructure\Search\Index\ScoutSearchIndexWriter;
use App\Infrastructure\Search\Index\SearchIndexWriterInterface;
use App\Infrastructure\Search\Listeners\SyncSearchOnModuleChange;
use App\Infrastructure\Search\Persistence\Eloquent\Models\SearchDocumentModel;
use App\Infrastructure\Search\Persistence\SearchDocumentStore;
use App\Infrastructure\Search\Services\SearchIndexer;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class SearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SearchDocumentStoreInterface::class, SearchDocumentStore::class);
        $this->app->bind(SearchIndexerInterface::class, SearchIndexer::class);

        // Meilisearch when Scout is configured for it, otherwise nothing: the database search reads the table
        $this->app->bind(SearchIndexWriterInterface::class, fn (Application $app): SearchIndexWriterInterface => $app->make('config')->get('scout.driver') === 'meilisearch'
            ? $app->make(ScoutSearchIndexWriter::class)
            : $app->make(NullSearchIndexWriter::class));
    }

    public function boot(): void
    {
        // Only the indexer writes to the search index: saving a document never pushes it on its own
        SearchDocumentModel::disableSearchSyncing();

        foreach ([ModuleEnabled::class, ModuleDisabled::class, ModuleUpdated::class, ModuleUninstalled::class, ModuleUpdateCompleted::class] as $event) {
            Event::listen($event, SyncSearchOnModuleChange::class);
        }
    }
}
