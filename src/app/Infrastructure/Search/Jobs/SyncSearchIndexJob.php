<?php

declare(strict_types=1);

namespace App\Infrastructure\Search\Jobs;

use App\Application\Modules\Services\EnabledModulesResolverInterface;
use App\Application\Search\Services\SearchIndexerInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Rebuilds the search documents of one type, or of every type, after modules change.
 */
final class SyncSearchIndexJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /** Seconds the uniqueness lock lasts, so a burst of changes queues a single sync */
    public int $uniqueFor = 300;

    public function __construct(
        public readonly ?string $type = null,
    ) {}

    public function uniqueId(): string
    {
        return $this->type ?? '*';
    }

    /**
     * Queues a sync without ever failing the caller: with a sync queue the job runs inline, and a broken queue throws on dispatch.
     */
    public static function dispatchSafely(?string $type = null): void
    {
        try {
            self::dispatch($type);
        } catch (Throwable $e) {
            Log::warning('[Search] Could not sync the search documents.', ['type' => $type, 'exception' => $e]);
        }
    }

    public function handle(SearchIndexerInterface $indexer, EnabledModulesResolverInterface $modules): void
    {
        // A long-lived worker memoized the enabled modules at boot: read them again so a module disabled since then counts
        $modules->reset();
        $result = $indexer->sync($this->type);

        if ($result->error !== null) {
            Log::warning('[Search] The search documents sync had problems.', ['type' => $this->type, 'error' => $result->error]);
        }
    }
}
