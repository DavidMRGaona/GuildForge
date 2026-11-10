<?php

declare(strict_types=1);

namespace App\Infrastructure\Media\Services;

use App\Application\Media\Services\MediaFileIdResolverInterface;
use App\Application\Media\Services\MediaFileReleaserInterface;
use App\Domain\Media\ValueObjects\MediaFileId;
use App\Infrastructure\Media\Jobs\ReleaseMediaFileJob;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Queues the live check after the commit, so a save that is rolled back releases nothing and the sources
 * read what was saved. Who caused it is taken now: the queue worker has nobody signed in.
 */
final readonly class MediaFileReleaser implements MediaFileReleaserInterface
{
    public function __construct(
        private MediaFileIdResolverInterface $resolver,
    ) {}

    public function release(string $storedPath): void
    {
        try {
            $id = $storedPath !== '' ? $this->resolver->fromStoredPath($storedPath) : null;

            if (! $id instanceof MediaFileId) {
                Log::info('[Media] A released path is not a file of this site; nothing was deleted.', ['path' => $storedPath]);

                return;
            }

            $actorId = Auth::id();

            ReleaseMediaFileJob::dispatch(
                $id->value,
                $this->resolver->resourceType($storedPath),
                is_string($actorId) && $actorId !== '' ? $actorId : null,
            )->afterCommit();
        } catch (Throwable $e) {
            Log::warning('[Media] A released file could not be queued for its check; it was kept.', [
                'path' => $storedPath,
                'exception' => $e,
            ]);
        }
    }
}
