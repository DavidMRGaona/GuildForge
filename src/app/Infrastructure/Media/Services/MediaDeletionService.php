<?php

declare(strict_types=1);

namespace App\Infrastructure\Media\Services;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Media\Contracts\MediaUsageSourceInterface;
use App\Application\Media\DTOs\MediaDeletionResultDTO;
use App\Application\Media\Services\MediaCatalogInterface;
use App\Application\Media\Services\MediaDeletionServiceInterface;
use App\Application\Media\Services\MediaFileIdResolverInterface;
use App\Application\Media\Services\MediaSourceRegistryInterface;
use App\Domain\Media\Enums\MediaDeletionRefusal;
use App\Domain\Media\Enums\MediaDestroyOutcome;
use App\Domain\Media\Enums\MediaFileStatus;
use App\Domain\Media\Exceptions\MediaDeletionDisabledException;
use App\Domain\Media\MediaFileStatusRule;
use App\Domain\Media\ValueObjects\MediaFileId;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaDeletionModel;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaFileModel;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaUsageModel;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Deletes media files only when they are provably unused right now. The index may be stale (a refresh or
 * a sync can be running), so each file is checked again against the live sources, and any check that
 * fails or cannot be made keeps the file. Nothing is written unless the remote store confirms.
 *
 * A record saved between the checks and the remote deletion could still start using the file; that window
 * (milliseconds per file) is accepted, since closing it would mean locking every source.
 */
final readonly class MediaDeletionService implements MediaDeletionServiceInterface
{
    public const string PERMISSION = 'media.delete';

    public function __construct(
        private MediaSourceRegistryInterface $registry,
        private MediaFileIdResolverInterface $resolver,
        private AuthorizationServiceInterface $authorization,
        private Repository $config,
        // The catalog is resolved only when a file is about to be deleted: building its client may fail
        private Container $container,
    ) {}

    public function isEnabled(): bool
    {
        try {
            return $this->config->get('media.delete_enabled') === true && $this->resolver->prefix() !== '';
        } catch (Throwable) {
            return false;
        }
    }

    public function delete(array $fileIds, object $actor): MediaDeletionResultDTO
    {
        if (! $this->isEnabled()) {
            throw MediaDeletionDisabledException::create();
        }

        $actorId = $this->actorId($actor);

        if ($actorId === null || ! $this->authorization->can($actor, self::PERMISSION)) {
            throw new AuthorizationException('This user may not delete media files.');
        }

        $deleted = 0;
        $missing = 0;
        $freedBytes = 0;
        /** @var array<string, MediaDeletionRefusal> $refused */
        $refused = [];
        /** @var array<string, true> $handled */
        $handled = [];

        foreach ($fileIds as $id) {
            if (isset($handled[$id->value])) {
                continue;
            }

            $handled[$id->value] = true;
            $checked = $this->check($id);

            if ($checked instanceof MediaDeletionRefusal) {
                $refused[$id->value] = $checked;

                continue;
            }

            [$file, $sourceType] = $checked;
            $outcome = $this->destroy($id, $file);

            if (! $outcome instanceof MediaDestroyOutcome) {
                $refused[$id->value] = MediaDeletionRefusal::CatalogFailed;

                continue;
            }

            $this->record($file, $sourceType, $outcome, $actorId);

            if ($outcome === MediaDestroyOutcome::Deleted) {
                $deleted++;
                $freedBytes += $file->bytes;
            } else {
                $missing++;
            }
        }

        return new MediaDeletionResultDTO($deleted, $missing, $freedBytes, $refused);
    }

    /**
     * Every check, in order, made now and not taken from the index.
     *
     * @return MediaDeletionRefusal|array{MediaFileModel, string} the refusal, or the indexed file and the type of the source claiming it
     */
    private function check(MediaFileId $id): MediaDeletionRefusal|array
    {
        try {
            $file = MediaFileModel::query()->where('file_id', $id->value)->first();

            if ($file === null) {
                return MediaDeletionRefusal::NotIndexed;
            }

            // A source that cannot be built cannot say whether it uses the file: nothing is deleted meanwhile
            if ($this->registry->unresolved() !== []) {
                return MediaDeletionRefusal::CheckFailed;
            }

            if ($this->inHiddenFolder($id)) {
                return MediaDeletionRefusal::Hidden;
            }

            $claimer = $this->registry->claimingSource($id);

            if (! $claimer instanceof MediaUsageSourceInterface) {
                return MediaDeletionRefusal::Unclaimed;
            }

            if ($claimer->hidden()) {
                return MediaDeletionRefusal::Hidden;
            }

            $sources = $this->registry->sources();
            $activeTypes = array_map(static fn (MediaUsageSourceInterface $source): string => $source->type(), $sources);
            $claimerType = $claimer->type();

            // The claiming source must be one of the active sources right now
            if (! in_array($claimerType, $activeTypes, true)) {
                return MediaDeletionRefusal::Unclaimed;
            }

            /** @var list<string> $indexedTypes */
            $indexedTypes = MediaUsageModel::query()
                ->where('file_id', $id->value)
                ->distinct()
                ->pluck('source_type')
                ->all();

            // Uses kept from a source that is no longer active (a disabled module) block the file
            if (array_diff($indexedTypes, $activeTypes) !== []) {
                return MediaDeletionRefusal::UsedByInactiveSource;
            }

            // Any active source may use a file outside its own folders
            foreach ($sources as $source) {
                $refusal = $this->usedBy($source, $id);

                if ($refusal instanceof MediaDeletionRefusal) {
                    return $refusal;
                }
            }

            // A use still in the index of an active source is a doubt too: the file stays until a sync clears it
            $status = MediaFileStatusRule::for(
                hasUsages: $indexedTypes !== [],
                claimedByActiveSource: true,
                uploadedAt: $file->uploaded_at->toImmutable(),
                now: CarbonImmutable::now(),
            );

            return match ($status) {
                MediaFileStatus::Unused => [$file, $claimerType],
                MediaFileStatus::InUse => MediaDeletionRefusal::InUse,
                MediaFileStatus::Recent => MediaDeletionRefusal::Recent,
                MediaFileStatus::UnknownOrigin => MediaDeletionRefusal::Unclaimed,
            };
        } catch (Throwable $e) {
            Log::warning('[Media] A file could not be checked before deleting it; it was kept.', [
                'file_id' => $id->value,
                'exception' => $e,
            ]);

            return MediaDeletionRefusal::CheckFailed;
        }
    }

    private function inHiddenFolder(MediaFileId $id): bool
    {
        return array_any($this->registry->hiddenFolders(), fn ($folder) => $folder !== '' && str_starts_with($id->value, $folder));
    }

    private function usedBy(MediaUsageSourceInterface $source, MediaFileId $id): ?MediaDeletionRefusal
    {
        try {
            return $source->usesFile($id) ? MediaDeletionRefusal::InUse : null;
        } catch (Throwable $e) {
            Log::warning('[Media] A media source could not tell whether it uses a file; the file was kept.', [
                'file_id' => $id->value,
                'source' => $source::class,
                'exception' => $e,
            ]);

            return MediaDeletionRefusal::CheckFailed;
        }
    }

    /**
     * @return MediaDestroyOutcome|null null when the store did not confirm
     */
    private function destroy(MediaFileId $id, MediaFileModel $file): ?MediaDestroyOutcome
    {
        try {
            return $this->container->make(MediaCatalogInterface::class)->destroy($id, $file->resource_type);
        } catch (Throwable $e) {
            Log::warning('[Media] The media store did not confirm a deletion; the file was kept in the index.', [
                'file_id' => $id->value,
                'exception' => $e,
            ]);

            return null;
        }
    }

    /**
     * Writes the deletion and drops the file from the index in one transaction. The remote file is gone by
     * now, so a failure here cannot be undone: it is logged with everything the record would have held.
     */
    private function record(MediaFileModel $file, string $sourceType, MediaDestroyOutcome $outcome, string $actorId): void
    {
        $row = [
            'file_id' => $file->file_id,
            'resource_type' => $file->resource_type,
            'folder' => $file->folder,
            'source_type' => $sourceType,
            'bytes' => $file->bytes,
            'width' => $file->width,
            'height' => $file->height,
            'outcome' => $outcome->value,
            'deleted_by' => $actorId,
            'deleted_at' => CarbonImmutable::now(),
        ];

        try {
            DB::transaction(static function () use ($row, $file): void {
                MediaDeletionModel::query()->create($row);
                MediaUsageModel::query()->where('file_id', $file->file_id)->delete();
                MediaFileModel::query()->where('file_id', $file->file_id)->delete();
            });
        } catch (Throwable $e) {
            Log::error('[Media] A file was removed from the media store but its deletion could not be recorded.', [
                ...$row,
                'deleted_at' => $row['deleted_at']->toIso8601String(),
                'exception' => $e,
            ]);
        }
    }

    private function actorId(object $actor): ?string
    {
        $id = match (true) {
            $actor instanceof Model => $actor->getKey(),
            $actor instanceof Authenticatable => $actor->getAuthIdentifier(),
            default => null,
        };

        return is_string($id) && $id !== '' ? $id : null;
    }
}
