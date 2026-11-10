<?php

declare(strict_types=1);

namespace App\Application\Media\DTOs;

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
     * @param  DateTimeImmutable|null  $lastSyncedAt  When the last reconciliation ended; null when it never ran
     */
    public function __construct(
        public int $files,
        public int $bytes,
        public int $unusedFiles,
        public int $unusedBytes,
        public ?DateTimeImmutable $lastSyncedAt,
    ) {}
}
