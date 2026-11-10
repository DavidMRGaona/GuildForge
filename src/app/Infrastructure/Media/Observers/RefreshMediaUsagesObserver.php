<?php

declare(strict_types=1);

namespace App\Infrastructure\Media\Observers;

use App\Application\Media\Services\MediaIndexerInterface;
use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Keeps the media usages of a record in line with the model it comes from: saving or deleting it queues a
 * refresh, which reads the record again (a deleted record has no usages). Public so modules can attach it to
 * their own models as model event listeners.
 */
final readonly class RefreshMediaUsagesObserver
{
    /**
     * @param  Closure(Model): list<string>  $recordIds  Ids, as the source knows them, of the records the model feeds
     */
    public function __construct(
        private string $type,
        private Closure $recordIds,
    ) {}

    public function saved(Model $model): void
    {
        $this->refresh($model);
    }

    public function deleted(Model $model): void
    {
        $this->refresh($model);
    }

    private function refresh(Model $model): void
    {
        $indexer = resolve(MediaIndexerInterface::class);

        foreach (($this->recordIds)($model) as $id) {
            $indexer->refresh($this->type, $id);
        }
    }
}
