<?php

declare(strict_types=1);

namespace App\Console\Commands\Search;

use App\Application\Search\Services\SearchIndexerInterface;
use Illuminate\Console\Command;

final class SearchSyncCommand extends Command
{
    protected $signature = 'search:sync {--type= : Rebuild only the documents of this type}';

    protected $description = 'Rebuild the site search documents from their sources and update the search index';

    public function handle(SearchIndexerInterface $indexer): int
    {
        $type = $this->option('type');
        $result = $indexer->sync(is_string($type) && $type !== '' ? $type : null);

        $this->info('Indexed: '.$result->indexed);
        $this->info('Removed: '.$result->removed);

        if ($result->error !== null) {
            $this->error($result->error);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
