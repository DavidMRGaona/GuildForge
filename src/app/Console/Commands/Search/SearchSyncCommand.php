<?php

declare(strict_types=1);

namespace App\Console\Commands\Search;

use App\Application\Search\Contracts\IndexedSearchSourceInterface;
use App\Application\Search\Services\SearchDocumentStoreInterface;
use App\Application\Search\Services\SearchIndexerInterface;
use App\Application\Search\Services\SearchSourceRegistryInterface;
use Illuminate\Console\Command;

final class SearchSyncCommand extends Command
{
    protected $signature = 'search:sync {--type= : Rebuild only the documents of this type}';

    protected $description = 'Rebuild the site search documents from their sources and update the search index';

    public function handle(SearchIndexerInterface $indexer, SearchSourceRegistryInterface $sources, SearchDocumentStoreInterface $store): int
    {
        $option = $this->option('type');
        $type = is_string($option) && $option !== '' ? $option : null;

        // A type no source offers and with no stored document is a typo: syncing it would end in 0/0 silently.
        // A type with stored documents but no source is an orphan, which the sync empties
        if ($type !== null) {
            $known = array_values(array_unique([
                ...array_map(static fn (IndexedSearchSourceInterface $source): string => $source->type(), $sources->indexedSources()),
                ...$store->types(),
            ]));

            if (! in_array($type, $known, true)) {
                sort($known);
                $this->error(sprintf('Unknown search type "%s". Known types: %s.', $type, $known === [] ? 'none' : implode(', ', $known)));

                return self::FAILURE;
            }
        }

        $result = $indexer->sync($type);

        $this->info('Indexed: '.$result->indexed);
        $this->info('Removed: '.$result->removed);

        if ($result->error !== null) {
            $this->error($result->error);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
