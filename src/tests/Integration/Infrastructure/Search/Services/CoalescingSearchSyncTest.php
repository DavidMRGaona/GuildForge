<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Search\Services;

use App\Infrastructure\Search\Services\CoalescingSearchSync;
use Illuminate\Contracts\Cache\Repository;
use Tests\Support\Search\FakeSearchSyncRunner;
use Tests\TestCase;

/**
 * A stale worker refreshing many rows of one type must not run one full sync per row: a sync that
 * succeeded and started after a job was queued already covers that job's row.
 */
final class CoalescingSearchSyncTest extends TestCase
{
    public function test_a_successful_sync_started_after_the_job_was_queued_covers_it(): void
    {
        $runner = new FakeSearchSyncRunner;
        $sync = $this->sync($runner);
        $queuedAt = microtime(true);

        $this->assertTrue($sync->syncCovering('example.type', $queuedAt));
        $this->assertTrue($sync->syncCovering('example.type', $queuedAt));

        $this->assertSame(['example.type'], $runner->runs);
    }

    public function test_a_job_queued_after_the_last_sync_started_syncs_again(): void
    {
        $runner = new FakeSearchSyncRunner;
        $sync = $this->sync($runner);

        $sync->syncCovering('example.type', microtime(true));
        $sync->syncCovering('example.type', microtime(true) + 1);

        $this->assertSame(['example.type', 'example.type'], $runner->runs);
    }

    public function test_each_type_syncs_on_its_own(): void
    {
        $runner = new FakeSearchSyncRunner;
        $sync = $this->sync($runner);
        $queuedAt = microtime(true);

        $sync->syncCovering('example.type', $queuedAt);
        $sync->syncCovering('other.type', $queuedAt);

        $this->assertSame(['example.type', 'other.type'], $runner->runs);
    }

    public function test_a_failed_sync_covers_nobody(): void
    {
        $runner = new FakeSearchSyncRunner(succeeds: false);
        $sync = $this->sync($runner);
        $queuedAt = microtime(true);

        $this->assertFalse($sync->syncCovering('example.type', $queuedAt));
        $this->assertFalse($sync->syncCovering('example.type', $queuedAt));

        $this->assertSame(['example.type', 'example.type'], $runner->runs);
    }

    public function test_a_job_queued_before_the_change_always_syncs(): void
    {
        $runner = new FakeSearchSyncRunner;
        $sync = $this->sync($runner);

        $sync->syncCovering('example.type', microtime(true));
        $sync->syncCovering('example.type', null);

        $this->assertSame(['example.type', 'example.type'], $runner->runs);
    }

    private function sync(FakeSearchSyncRunner $runner): CoalescingSearchSync
    {
        return new CoalescingSearchSync($runner, $this->app->make(Repository::class));
    }
}
