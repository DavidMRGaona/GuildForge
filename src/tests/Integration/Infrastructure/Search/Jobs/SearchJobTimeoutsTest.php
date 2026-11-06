<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Search\Jobs;

use App\Infrastructure\Search\Jobs\RefreshSearchDocumentJob;
use App\Infrastructure\Search\Jobs\SyncSearchIndexJob;
use App\Infrastructure\Search\Services\CoalescingSearchSync;
use App\Infrastructure\Search\Services\ProcessSearchSyncRunner;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A search job may run `search:sync` in a fresh process: the worker must not kill it first (its own 60 s
 * default), and the queue must not hand it to another worker while it still runs (retry_after).
 */
final class SearchJobTimeoutsTest extends TestCase
{
    public function test_the_sync_job_outlives_the_sync_process(): void
    {
        $this->assertGreaterThan(ProcessSearchSyncRunner::TIMEOUT_SECONDS, (new SyncSearchIndexJob)->timeout);
    }

    public function test_the_refresh_job_outlives_waiting_for_another_sync_and_running_its_own(): void
    {
        $this->assertGreaterThan(
            CoalescingSearchSync::LOCK_WAIT_SECONDS + ProcessSearchSyncRunner::TIMEOUT_SECONDS,
            (new RefreshSearchDocumentJob('example.type', '1'))->timeout,
        );
    }

    #[DataProvider('queueConnections')]
    public function test_the_queue_never_retries_a_search_job_that_still_runs(string $connection): void
    {
        $retryAfter = config("queue.connections.{$connection}.retry_after");
        $this->assertIsInt($retryAfter);

        $this->assertLessThan($retryAfter, (new SyncSearchIndexJob)->timeout);
        $this->assertLessThan($retryAfter, (new RefreshSearchDocumentJob('example.type', '1'))->timeout);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function queueConnections(): iterable
    {
        yield 'database' => ['database'];
        yield 'redis' => ['redis'];
    }
}
