<?php

declare(strict_types=1);

namespace App\Infrastructure\Search\Services;

use App\Application\Search\Services\SearchSyncRunnerInterface;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository;

/**
 * One fresh-process sync of a type at a time, skipped when a sync of that type already succeeded after
 * the job asking for it was queued: that sync read the rows as they were then, so it covers the job's row.
 * A stale worker refreshing many rows of one type then runs a single full sync instead of one per row.
 *
 * Fails closed: a cache that cannot be reached, or a lock not freed in time, throws, and the caller
 * treats it as a failed sync.
 */
final readonly class CoalescingSearchSync
{
    /** Seconds to wait for a sync of the same type that is still running */
    public const int LOCK_WAIT_SECONDS = ProcessSearchSyncRunner::TIMEOUT_SECONDS;

    public function __construct(
        private SearchSyncRunnerInterface $runner,
        private Repository $cache,
    ) {}

    /**
     * @param  float|null  $queuedAt  When the job asking for the sync was queued (microtime(true)); null is never covered
     *
     * @throws LockTimeoutException
     */
    public function syncCovering(string $type, ?float $queuedAt): bool
    {
        $store = $this->cache->getStore();

        // Every store but the null one can lock; without locks, nothing can be coalesced
        if (! $store instanceof LockProvider) {
            return $this->runner->sync($type);
        }

        $lock = $store->lock("search-sync-lock:{$type}", ProcessSearchSyncRunner::TIMEOUT_SECONDS + 60);

        return $lock->block(self::LOCK_WAIT_SECONDS, function () use ($type, $queuedAt): bool {
            $key = "search-sync-succeeded-at:{$type}";
            $lastStart = $this->cache->get($key);

            if ($queuedAt !== null && is_float($lastStart) && $lastStart >= $queuedAt) {
                return true;
            }

            $startedAt = microtime(true);
            $synced = $this->runner->sync($type);

            if ($synced) {
                $this->cache->put($key, $startedAt, 86_400);
            }

            return $synced;
        });
    }
}
