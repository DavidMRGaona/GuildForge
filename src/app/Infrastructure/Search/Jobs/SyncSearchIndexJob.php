<?php

declare(strict_types=1);

namespace App\Infrastructure\Search\Jobs;

use App\Application\Search\Services\SearchSyncRunnerInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Rebuilds the search documents of one type, or of every type, after modules change.
 *
 * The sync runs in a fresh PHP process: this one (a request or a long-lived worker) booted with the previous
 * set of modules, so its source registry lacks the sources of a module enabled since.
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

    public function handle(SearchSyncRunnerInterface $runner): void
    {
        $runner->sync($this->type);
    }
}
