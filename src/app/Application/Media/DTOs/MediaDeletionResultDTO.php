<?php

declare(strict_types=1);

namespace App\Application\Media\DTOs;

use App\Domain\Media\Enums\MediaDeletionRefusal;

/**
 * Outcome of a request to delete media files.
 */
final readonly class MediaDeletionResultDTO
{
    /**
     * @param  int  $deleted  Files the remote store confirmed as deleted
     * @param  int  $missing  Files the remote store no longer had; removed from the index and recorded as missing
     * @param  int  $freedBytes  Bytes of the deleted files (missing ones free nothing)
     * @param  array<string, MediaDeletionRefusal>  $refused  Files kept, by file id, with the reason
     */
    public function __construct(
        public int $deleted,
        public int $missing,
        public int $freedBytes,
        public array $refused,
    ) {}
}
