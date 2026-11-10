<?php

declare(strict_types=1);

namespace App\Application\Media\DTOs;

/**
 * Outcome of a media library reconciliation.
 */
final readonly class MediaSyncResultDTO
{
    /**
     * @param  bool  $catalogComplete  False when the remote listing failed: no file was added or removed
     * @param  int  $files  Files in the index after the sync
     * @param  int  $added  Files added to the index
     * @param  int  $removed  Files removed from the index
     * @param  list<string>  $failedSources  Types whose usages could not be read; their rows were left as they were
     */
    public function __construct(
        public bool $catalogComplete,
        public int $files,
        public int $added,
        public int $removed,
        public array $failedSources,
    ) {}
}
