<?php

declare(strict_types=1);

namespace App\Infrastructure\Media\Jobs;

use App\Application\Media\Services\MediaIndexerInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs the media library reconciliation on the queue (the panel's «Actualizar» button), one at a time.
 */
final class SyncMediaLibraryJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /** A failed sync is not retried: the next one (nightly or by hand) starts over */
    public int $tries = 1;

    /** Less than the queue's retry_after (660 s), so the job is never handed to a second worker */
    public int $timeout = 600;

    /** Seconds the uniqueness lock lasts at most, should a worker die without releasing it */
    public int $uniqueFor = 900;

    /**
     * Queues a sync without ever failing the caller: with a sync queue the job runs inline, and a broken
     * queue throws on dispatch. False when it could not be queued.
     */
    public static function dispatchSafely(): bool
    {
        try {
            self::dispatch();

            return true;
        } catch (Throwable $e) {
            Log::warning('[Media] Could not queue a media library sync.', ['exception' => $e]);

            return false;
        }
    }

    public function handle(MediaIndexerInterface $indexer): void
    {
        $indexer->sync();
    }
}
