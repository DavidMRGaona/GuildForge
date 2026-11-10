<?php

declare(strict_types=1);

namespace App\Infrastructure\Media\Services;

use App\Application\Media\Contracts\MediaUsageSourceInterface;
use App\Application\Media\DTOs\MediaSyncResultDTO;
use App\Application\Media\Services\MediaCatalogInterface;
use App\Application\Media\Services\MediaIndexerInterface;
use App\Application\Media\Services\MediaSourceRegistryInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Infrastructure\Media\Jobs\RefreshMediaUsagesJob;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaFileModel;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaUsageModel;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Exception;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reconciles the media library index. Fails closed: a remote listing that does not finish removes no file,
 * a source whose usages cannot be read keeps its rows, and rows of types without an active source (a module
 * disabled or uninstalled) are never touched here (see media:forget-source).
 */
final readonly class MediaIndexer implements MediaIndexerInterface
{
    public const string LAST_SYNCED_KEY = 'media_library_last_synced_at';

    public function __construct(
        private MediaSourceRegistryInterface $registry,
        private MediaIndexWriter $writer,
        private SettingsServiceInterface $settings,
        private Dispatcher $bus,
        // The catalog is resolved only by sync(): refresh() runs on every save and must not depend on it
        private Container $container,
    ) {}

    public function refresh(string $type, string $recordId): void
    {
        try {
            $this->bus->dispatch((new RefreshMediaUsagesJob($type, $recordId))->afterCommit());
        } catch (Throwable $e) {
            Log::warning('[Media] Could not queue a media usages refresh.', [
                'type' => $type,
                'record_id' => $recordId,
                'exception' => $e,
            ]);
        }
    }

    public function sync(): MediaSyncResultDTO
    {
        // Stored instants have whole seconds: every file seen by this sync gets exactly this mark
        $startedAt = CarbonImmutable::now()->startOfSecond();

        [$catalogComplete, $added, $removed] = $this->syncFiles($startedAt);

        $failedSources = [];

        foreach ($this->registry->sources() as $source) {
            $failed = $this->syncSource($source);

            if ($failed !== null) {
                $failedSources[] = $failed;
            }
        }

        $this->settings->set(self::LAST_SYNCED_KEY, CarbonImmutable::now('UTC')->toIso8601String());

        return new MediaSyncResultDTO(
            catalogComplete: $catalogComplete,
            files: MediaFileModel::query()->count(),
            added: $added,
            removed: $removed,
            failedSources: $failedSources,
        );
    }

    public function lastSyncedAt(): ?DateTimeImmutable
    {
        $value = $this->settings->get(self::LAST_SYNCED_KEY);

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Applies the remote listing only when it is complete: every listed file is marked as seen now, and the
     * files not seen are removed. A file added by a refresh while this runs is marked later and stays.
     *
     * @return array{bool, int, int} whether the listing was complete, files added and files removed
     */
    private function syncFiles(CarbonImmutable $startedAt): array
    {
        try {
            $assets = $this->container->make(MediaCatalogInterface::class)->all();
        } catch (Throwable $e) {
            Log::warning('[Media] The media catalog could not be listed; no file was added or removed.', ['exception' => $e]);

            return [false, 0, 0];
        }

        $added = $this->writer->upsertFiles($assets, $startedAt);
        $removed = MediaFileModel::query()
            ->where('last_seen_at', '<', (new MediaFileModel)->fromDateTime($startedAt))
            ->delete();

        return [true, $added, $removed];
    }

    /**
     * Replaces the usage rows of one source, all or nothing: they are written in chunks while its usages are
     * read, inside a transaction that a failing source rolls back.
     *
     * @return string|null the type (or class) of the source when it failed
     */
    private function syncSource(MediaUsageSourceInterface $source): ?string
    {
        $type = null;

        try {
            $type = $source->type();
            $module = $this->registry->moduleOf($type);

            DB::transaction(function () use ($source, $type, $module): void {
                /** @var array<string, true> $seen */
                $seen = [];
                $pending = [];

                foreach ($source->usages() as $usage) {
                    $row = $this->writer->usageRow($usage, $type, $module);

                    if ($row === null) {
                        continue;
                    }

                    $key = MediaIndexWriter::usageKey($row['file_id'], $row['record_id'], $row['role']);
                    $pending[$key] = $row;
                    $seen[$key] = true;

                    if (count($pending) >= MediaIndexWriter::CHUNK) {
                        $this->writer->upsertUsages($pending);
                        $pending = [];
                    }
                }

                $this->writer->upsertUsages($pending);
                $this->deleteUnseen($type, $seen);
            });
        } catch (Throwable $e) {
            Log::warning('[Media] The usages of a media source could not be synced; its rows were left as they were.', [
                'type' => $type,
                'source' => $source::class,
                'exception' => $e,
            ]);

            return $type ?? $source::class;
        }

        return null;
    }

    /**
     * @param  array<string, true>  $seen
     */
    private function deleteUnseen(string $type, array $seen): void
    {
        $stale = [];
        $rows = MediaUsageModel::query()
            ->where('source_type', $type)
            ->select(['id', 'file_id', 'record_id', 'role'])
            ->lazyById(MediaIndexWriter::CHUNK);

        foreach ($rows as $row) {
            if (isset($seen[MediaIndexWriter::usageKey($row->file_id, $row->record_id, $row->role)])) {
                continue;
            }

            $stale[] = $row->id;

            if (count($stale) >= MediaIndexWriter::CHUNK) {
                MediaUsageModel::query()->whereIn('id', $stale)->delete();
                $stale = [];
            }
        }

        if ($stale !== []) {
            MediaUsageModel::query()->whereIn('id', $stale)->delete();
        }
    }
}
