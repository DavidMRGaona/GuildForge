<?php

declare(strict_types=1);

namespace App\Infrastructure\Media\Services;

use DateTimeImmutable;

/**
 * Totals of the media library, without the files of hidden sources.
 */
final readonly class MediaLibraryStatsDTO
{
    /**
     * @param  int  $files  Files listed in the library
     * @param  int  $bytes  Space they take
     * @param  int  $unusedFiles  Files that could be deleted (unused and past the grace period)
     * @param  int  $unusedBytes  Space deleting them would free
     * @param  DateTimeImmutable|null  $lastSyncedAt  When the last complete reconciliation ended; null when none has
     * @param  DateTimeImmutable|null  $lastSyncFailedAt  When the last incomplete reconciliation ended; null when none failed
     */
    public function __construct(
        public int $files,
        public int $bytes,
        public int $unusedFiles,
        public int $unusedBytes,
        public ?DateTimeImmutable $lastSyncedAt,
        public ?DateTimeImmutable $lastSyncFailedAt,
    ) {}

    /**
     * Whether the newest reconciliation failed, so the totals may be out of date.
     */
    public function lastSyncFailed(): bool
    {
        return $this->lastSyncFailedAt instanceof DateTimeImmutable
            && (! $this->lastSyncedAt instanceof DateTimeImmutable || $this->lastSyncFailedAt > $this->lastSyncedAt);
    }
}
