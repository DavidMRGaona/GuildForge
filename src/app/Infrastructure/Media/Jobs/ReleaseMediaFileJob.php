<?php

declare(strict_types=1);

namespace App\Infrastructure\Media\Jobs;

use App\Application\Media\Services\MediaDeletionServiceInterface;
use App\Domain\Media\Enums\MediaDeletionRefusal;
use App\Domain\Media\ValueObjects\MediaFileId;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Deletes a file a record let go of, only if nobody uses it right now. Only an unreachable store is tried
 * again; any other refusal keeps the file, which then shows as unused in the library.
 */
final class ReleaseMediaFileJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    private const int RETRY_SECONDS = 60;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public readonly string $fileId,
        public readonly string $resourceType,
        public readonly ?string $actorId,
    ) {}

    public function handle(MediaDeletionServiceInterface $deletions): void
    {
        try {
            $refusal = $deletions->releaseIfUnused(new MediaFileId($this->fileId), $this->resourceType, $this->actorId);
        } catch (Throwable $e) {
            Log::warning('[Media] A released file could not be checked; it was kept.', [
                'file_id' => $this->fileId,
                'exception' => $e,
            ]);

            return;
        }

        if ($refusal === MediaDeletionRefusal::CatalogFailed && $this->attempts() < $this->tries) {
            $this->release(self::RETRY_SECONDS);
        }
    }
}
