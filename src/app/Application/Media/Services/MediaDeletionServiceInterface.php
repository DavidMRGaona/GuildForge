<?php

declare(strict_types=1);

namespace App\Application\Media\Services;

use App\Application\Media\DTOs\MediaDeletionResultDTO;
use App\Domain\Media\Enums\MediaDeletionRefusal;
use App\Domain\Media\Exceptions\MediaDeletionDisabledException;
use App\Domain\Media\ValueObjects\MediaFileId;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * The only way to delete files from the media library. Fails closed: every check is made again at delete
 * time, without trusting the index, and any doubt keeps the file.
 */
interface MediaDeletionServiceInterface
{
    /**
     * Whether this installation may delete at all: the switch is on and the storage prefix is not empty.
     */
    public function isEnabled(): bool;

    /**
     * Deletes the given files that are provably unused; the others are returned as refused.
     *
     * @param  list<MediaFileId>  $fileIds
     *
     * @throws MediaDeletionDisabledException when isEnabled() is false
     * @throws AuthorizationException when the actor lacks media.delete
     */
    public function delete(array $fileIds, object $actor): MediaDeletionResultDTO;

    /**
     * Deletes a file a record let go of, if nobody uses it right now: the same live checks as delete(), but
     * the file need not be indexed, there is no grace period, and neither the switch nor media.delete apply
     * (saving a record the person may edit caused it). An empty storage prefix keeps every file.
     *
     * @return MediaDeletionRefusal|null null when it was deleted or was already gone; the refusal otherwise
     */
    public function releaseIfUnused(MediaFileId $id, string $resourceType, ?string $actorId): ?MediaDeletionRefusal;
}
