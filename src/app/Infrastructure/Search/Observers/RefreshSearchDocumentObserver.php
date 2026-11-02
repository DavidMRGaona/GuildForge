<?php

declare(strict_types=1);

namespace App\Infrastructure\Search\Observers;

use App\Application\Search\Services\SearchIndexerInterface;
use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Keeps one search document in line with the model it comes from: saving refreshes it, deleting forgets it.
 *
 * A model that only contributes to another record's document (a photo in a gallery) passes that record's id
 * and does not forget it when deleted, because the record still exists.
 */
final readonly class RefreshSearchDocumentObserver
{
    /**
     * @param  Closure(Model): string  $documentId
     */
    public function __construct(
        private string $type,
        private Closure $documentId,
        private bool $forgetOnDelete = true,
    ) {}

    public function saved(Model $model): void
    {
        resolve(SearchIndexerInterface::class)->refresh($this->type, ($this->documentId)($model));
    }

    public function deleted(Model $model): void
    {
        $indexer = resolve(SearchIndexerInterface::class);
        $id = ($this->documentId)($model);

        if ($this->forgetOnDelete) {
            $indexer->forget($this->type, $id);

            return;
        }

        $indexer->refresh($this->type, $id);
    }
}
