<?php

declare(strict_types=1);

namespace App\Infrastructure\Media\Jobs;

use App\Application\Media\Contracts\MediaUsageSourceInterface;
use App\Application\Media\DTOs\MediaAssetDTO;
use App\Application\Media\Services\MediaCatalogInterface;
use App\Application\Media\Services\MediaFileIdResolverInterface;
use App\Application\Media\Services\MediaSourceRegistryInterface;
use App\Domain\Media\ValueObjects\MediaFileId;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaFileModel;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaUsageModel;
use App\Infrastructure\Media\Services\MediaIndexWriter;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Brings the usage rows of one record in line with its source, then the files they touch: a used file
 * missing from the index is looked up and added, and a file that lost its last usage is removed once the
 * catalog says it no longer exists.
 *
 * A type without an active source keeps its rows (the module may only be disabled in this worker). Any
 * failure is logged and the job ends: with the sync queue it runs at commit time, where an exception would
 * reach the request; the nightly sync settles what was left.
 */
final class RefreshMediaUsagesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /** Only image resources are listed by the catalog, so only those are looked up */
    private const string LISTED_RESOURCE_TYPE = 'image';

    public int $timeout = 120;

    public function __construct(
        public readonly string $type,
        public readonly string $recordId,
    ) {}

    public function handle(
        MediaSourceRegistryInterface $registry,
        MediaIndexWriter $writer,
        MediaFileIdResolverInterface $resolver,
        MediaCatalogInterface $catalog,
    ): void {
        try {
            $source = $registry->source($this->type);

            if (! $source instanceof MediaUsageSourceInterface) {
                return;
            }

            $module = $registry->moduleOf($this->type);
            $recordId = MediaIndexWriter::recordId($this->recordId);
            $rows = [];
            /** @var array<string, string> $paths stored path of each used file */
            $paths = [];

            foreach ($source->usagesFor($this->recordId) as $usage) {
                $row = $writer->usageRow($usage, $this->type, $module);

                // A usage of another record would never be replaced by this record's refresh
                if ($row === null || $row['record_id'] !== $recordId) {
                    continue;
                }

                $rows[MediaIndexWriter::usageKey($row['file_id'], $row['record_id'], $row['role'])] = $row;
                $paths[$row['file_id']] ??= $usage->storedPath;
            }

            $before = DB::transaction(function () use ($recordId, $rows, $writer): array {
                $before = [];
                $stale = [];
                $existing = MediaUsageModel::query()
                    ->where('source_type', $this->type)
                    ->where('record_id', $recordId)
                    ->get(['id', 'file_id', 'record_id', 'role']);

                foreach ($existing as $row) {
                    $before[$row->file_id] = true;

                    if (! isset($rows[MediaIndexWriter::usageKey($row->file_id, $row->record_id, $row->role)])) {
                        $stale[] = $row->id;
                    }
                }

                if ($stale !== []) {
                    MediaUsageModel::query()->whereIn('id', $stale)->delete();
                }

                $writer->upsertUsages($rows);

                return $before;
            });

            $this->addMissingFiles($paths, $writer, $resolver, $catalog);
            $this->dropLostFiles(array_keys(array_diff_key($before, $paths)), $catalog);
        } catch (Throwable $e) {
            $this->warn('[Media] The media usages of a record could not be refreshed; the nightly sync will settle them.', $e);
        }
    }

    /**
     * @param  array<string, string>  $paths
     */
    private function addMissingFiles(array $paths, MediaIndexWriter $writer, MediaFileIdResolverInterface $resolver, MediaCatalogInterface $catalog): void
    {
        if ($paths === []) {
            return;
        }

        $known = MediaFileModel::query()->whereIn('file_id', array_map(strval(...), array_keys($paths)))->pluck('file_id')->all();

        foreach (array_diff_key($paths, array_flip($known)) as $fileId => $storedPath) {
            if ($resolver->resourceType($storedPath) !== self::LISTED_RESOURCE_TYPE) {
                continue;
            }

            try {
                $asset = $catalog->find(new MediaFileId((string) $fileId), self::LISTED_RESOURCE_TYPE);
            } catch (Throwable $e) {
                // The catalog is down: the nightly sync adds what is left
                $this->warn('[Media] A used file could not be looked up; it is added by the nightly sync.', $e);

                return;
            }

            if ($asset instanceof MediaAssetDTO) {
                $writer->upsertFiles([$asset], CarbonImmutable::now());
            }
        }
    }

    /**
     * @param  list<array-key>  $fileIds
     */
    private function dropLostFiles(array $fileIds, MediaCatalogInterface $catalog): void
    {
        foreach ($fileIds as $fileId) {
            $fileId = (string) $fileId;

            if (MediaUsageModel::query()->where('file_id', $fileId)->exists()) {
                continue;
            }

            $file = MediaFileModel::query()->where('file_id', $fileId)->first();

            if ($file === null) {
                continue;
            }

            try {
                $asset = $catalog->find(new MediaFileId($fileId), $file->resource_type);
            } catch (Throwable $e) {
                // Kept: an unused file the catalog cannot vouch for stays listed until the nightly sync
                $this->warn('[Media] A file that lost its last usage could not be looked up; it was kept.', $e);

                return;
            }

            if (! $asset instanceof MediaAssetDTO) {
                // A usage added meanwhile keeps the file
                MediaFileModel::query()->where('file_id', $fileId)->whereDoesntHave('usages')->delete();
            }
        }
    }

    private function warn(string $message, Throwable $e): void
    {
        Log::warning($message, [
            'type' => $this->type,
            'record_id' => $this->recordId,
            'exception' => $e,
        ]);
    }
}
