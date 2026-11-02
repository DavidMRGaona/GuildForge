<?php

declare(strict_types=1);

namespace App\Infrastructure\Search\Index;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * One run's writes to the search engine (a sync or a refresh): failures are logged and collected, never thrown.
 *
 * After the first failure the engine is left alone for the rest of the run, so one outage is one timeout, not
 * one per chunk. Puts need nothing more: every sync puts every document again. Removals do, since their rows
 * are already gone: a removal that fails or is skipped is recorded in PendingSearchRemovals and replayed by
 * the next sync or refresh.
 */
final class SearchIndexMirror
{
    private bool $engineUp = true;

    /** @var list<string> */
    private array $errors = [];

    public function __construct(
        private readonly SearchIndexWriterInterface $writer,
        private readonly PendingSearchRemovals $pending,
    ) {}

    public function syncSettings(): void
    {
        $this->write(function (): void {
            $this->writer->syncSettings();
        });
    }

    /**
     * @param  list<string>  $rowIds
     */
    public function put(array $rowIds): void
    {
        if ($rowIds !== []) {
            $this->write(function () use ($rowIds): void {
                $this->writer->put($rowIds);
            });
        }
    }

    /**
     * @param  list<string>  $rowIds  ids of rows already removed from the table
     */
    public function remove(array $rowIds): void
    {
        if ($rowIds === []) {
            return;
        }

        $sent = $this->write(function () use ($rowIds): void {
            $this->writer->remove($rowIds);
        });

        if (! $sent) {
            $this->pending->record($rowIds);
        }
    }

    /**
     * Sends the removals earlier runs could not send; each chunk is cleared once the engine took it.
     */
    public function replayPendingRemovals(): void
    {
        foreach ($this->pending->chunks() as $rowIds) {
            $sent = $this->write(function () use ($rowIds): void {
                $this->writer->remove($rowIds);
            });

            if (! $sent) {
                return;
            }

            $this->pending->clear($rowIds);
        }
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @param  callable(): void  $write
     * @return bool whether the write reached the engine
     */
    private function write(callable $write): bool
    {
        if (! $this->engineUp) {
            return false;
        }

        try {
            $write();

            return true;
        } catch (Throwable $e) {
            $this->engineUp = false;
            Log::warning('[Search] The search index could not be updated.', ['exception' => $e]);
            $this->errors[] = 'Search index not updated: '.$e->getMessage();

            return false;
        }
    }
}
