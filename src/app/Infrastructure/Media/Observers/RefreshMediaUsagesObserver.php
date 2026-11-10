<?php

declare(strict_types=1);

namespace App\Infrastructure\Media\Observers;

use App\Application\Media\Services\MediaIndexerInterface;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Keeps the media usages of a record in line with the model it comes from: saving or deleting it queues a
 * refresh, which reads the record again (a deleted record has no usages). Public so modules can attach it to
 * their own models as model event listeners.
 *
 * It never fails the save: anything that goes wrong is logged and the nightly sync settles the record.
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
        try {
            $ids = ($this->recordIds)($model);

            // Most saves of some models (accounts on every sign-in) have nothing to refresh
            if ($ids === []) {
                return;
            }

            $indexer = resolve(MediaIndexerInterface::class);

            foreach ($ids as $id) {
                $indexer->refresh($this->type, $id);
            }
        } catch (Throwable $e) {
            Log::warning('[Media] Could not refresh the media usages of a saved record.', [
                'type' => $this->type,
                'model' => $model::class,
                'exception' => $e,
            ]);
        }
    }
}
