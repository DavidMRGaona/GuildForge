<?php

declare(strict_types=1);

namespace App\Application\Media\Services;

use App\Application\Media\DTOs\MediaSyncResultDTO;
use DateTimeImmutable;

/**
 * Keeps the media library index (files and their usages) in line with the remote store and the sources.
 */
interface MediaIndexerInterface
{
    /**
     * Queues a refresh of the usages of one record, after the current transaction commits. Never throws:
     * a queue that cannot take the job is logged and the nightly sync settles the record.
     */
    public function refresh(string $type, string $recordId): void;

    /**
     * Reconciles the whole index: files from a complete remote listing (none added or removed otherwise)
     * and the usages of every active source (a failing source keeps its rows).
     */
    public function sync(): MediaSyncResultDTO;

    /**
     * When the last sync ended, or null when it never ran.
     */
    public function lastSyncedAt(): ?DateTimeImmutable;
}
