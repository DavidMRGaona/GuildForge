<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Search\Services\SearchDocumentStoreInterface;
use App\Infrastructure\Search\Persistence\Eloquent\Models\SearchDocumentModel;
use App\Infrastructure\Search\Persistence\SearchDocumentStore;
use Illuminate\Support\ServiceProvider;

final class SearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SearchDocumentStoreInterface::class, SearchDocumentStore::class);
    }

    public function boot(): void
    {
        // Only the indexer writes to the search index: saving a document never pushes it on its own
        SearchDocumentModel::disableSearchSyncing();
    }
}
