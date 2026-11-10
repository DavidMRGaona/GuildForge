<?php

declare(strict_types=1);

namespace App\Infrastructure\Media\Services;

use App\Application\Media\DTOs\MediaAssetDTO;
use App\Application\Media\DTOs\MediaUsageDTO;
use App\Application\Media\Services\MediaFileIdResolverInterface;
use App\Domain\Media\ValueObjects\MediaFileId;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaFileModel;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaUsageModel;
use DateTimeInterface;

/**
 * Writes rows of the media library index in chunks, shared by the nightly sync and the per-record refresh.
 *
 * Upserts bypass the model casts: instants are formatted by the model itself, in the storage zone.
 */
final readonly class MediaIndexWriter
{
    public const int CHUNK = 500;

    private const int RECORD_ID_LENGTH = 100;

    private const int ROLE_LENGTH = 100;

    private const int TITLE_LENGTH = 255;

    public function __construct(
        private MediaFileIdResolverInterface $resolver,
    ) {}

    /**
     * The usage row of a use, or null when the stored path is not a file of this site.
     *
     * Values longer than their column are cut rather than dropped: a dropped usage would make a used file
     * look unused.
     *
     * @return array{file_id: string, source_type: string, module: ?string, record_id: string, role: string, title: string}|null
     */
    public function usageRow(MediaUsageDTO $usage, string $type, ?string $module): ?array
    {
        $file = $this->resolver->fromStoredPath($usage->storedPath);

        if (! $file instanceof MediaFileId || $usage->recordId === '') {
            return null;
        }

        return [
            'file_id' => $file->value,
            'source_type' => $type,
            'module' => $module,
            'record_id' => self::recordId($usage->recordId),
            'role' => mb_substr($usage->role, 0, self::ROLE_LENGTH),
            'title' => mb_substr($usage->title, 0, self::TITLE_LENGTH),
        ];
    }

    /**
     * A record id as stored in the index.
     */
    public static function recordId(string $recordId): string
    {
        return mb_substr($recordId, 0, self::RECORD_ID_LENGTH);
    }

    /**
     * What tells two usage rows of one source apart (the unique key without the source type).
     */
    public static function usageKey(string $fileId, string $recordId, string $role): string
    {
        return $fileId."\0".$recordId."\0".$role;
    }

    /**
     * @param  array<array{file_id: string, source_type: string, module: ?string, record_id: string, role: string, title: string}>  $rows  without repeated keys
     */
    public function upsertUsages(array $rows): void
    {
        foreach (array_chunk(array_values($rows), self::CHUNK) as $chunk) {
            MediaUsageModel::query()->upsert(
                $chunk,
                ['file_id', 'source_type', 'record_id', 'role'],
                ['module', 'title'],
            );
        }
    }

    /**
     * Adds or updates the files, marked as seen at the given instant.
     *
     * @param  list<MediaAssetDTO>  $assets
     * @return int files that were not in the index
     */
    public function upsertFiles(array $assets, DateTimeInterface $seenAt): int
    {
        $model = new MediaFileModel;
        $seen = $model->fromDateTime($seenAt);
        $byId = [];

        foreach ($assets as $asset) {
            $byId[$asset->id->value] = [
                'file_id' => $asset->id->value,
                'resource_type' => $asset->resourceType,
                'folder' => $asset->id->folder(),
                'format' => $asset->format,
                'bytes' => $asset->bytes,
                'width' => $asset->width,
                'height' => $asset->height,
                'uploaded_at' => $model->fromDateTime($asset->uploadedAt),
                'last_seen_at' => $seen,
            ];
        }

        $added = 0;

        foreach (array_chunk($byId, self::CHUNK, preserve_keys: true) as $chunk) {
            $known = MediaFileModel::query()->whereIn('file_id', array_map(strval(...), array_keys($chunk)))->count();
            $added += count($chunk) - $known;

            MediaFileModel::query()->upsert(
                array_values($chunk),
                ['file_id'],
                ['resource_type', 'folder', 'format', 'bytes', 'width', 'height', 'uploaded_at', 'last_seen_at'],
            );
        }

        return $added;
    }
}
