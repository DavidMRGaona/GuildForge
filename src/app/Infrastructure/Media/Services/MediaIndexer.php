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
    /** When the last complete reconciliation ended: the whole catalog listed and every source read */
    public const string LAST_SYNCED_KEY = 'media_library_last_synced_at';

    /** When the last reconciliation that was not complete ended */
    public const string LAST_SYNC_FAILED_KEY = 'media_library_last_sync_failed_at';

    public function __construct(
        private MediaSourceRegistryInterface $registry,
        private MediaIndexWriter $writer,
        private SettingsServiceInterface $settings,
        private Dispatcher $bus,
        // The catalog is resolved only by sync(): refresh() runs on every save and must not depend on it
        private Container $container,
        private MediaSourceSyncState $syncState,
    ) {}

    public function refresh(string $type, string $recordId): void
    {
        try {
            $this->bus->dispatch((new RefreshMediaUsagesJob($type, $recordId))->afterCommit());
        } catch (Throwable $e) {
            Log::warning('[Media] Could not queue a media usages refresh; the type is concealed until the next sync.', [
                'type' => $type,
                'record_id' => $recordId,
                'exception' => $e,
            ]);
            // The refresh is lost: the index may keep a usage shown that the source now hides (core#844)
            $this->syncState->markRefreshFailed($type);
        }
    }

    public function sync(): MediaSyncResultDTO
    {
        // Stored instants have whole seconds: every file seen by this sync gets exactly this mark
        $startedAt = CarbonImmutable::now()->startOfSecond();

        [$catalogComplete, $added, $removed] = $this->syncFiles($startedAt);

        $failedSources = [];
        $sources = $this->registry->sources();
        // What each source is before its usages are read: a module updated meanwhile is not taken as synced
        $before = $this->syncState->fingerprints($sources);
        // Refresh failures seen before reading: one written while a source is read may come from a change
        // the read missed, so it outlives this sync (core#844). Unreadable ones are all kept
        $refreshFailures = $this->syncState->refreshFailures() ?? [];
        $succeeded = [];

        foreach ($sources as $source) {
            $failed = $this->syncSource($source);

            if ($failed !== null) {
                $failedSources[] = $failed;

                continue;
            }

            $succeeded[] = $source;
        }

        // The library shows a source's images only while its rows come from a sync of the source as it is now
        $after = $this->syncState->fingerprints($succeeded);
        $this->syncState->record(
            array_filter($after, static fn (string $fingerprint, string $type): bool => ($before[$type] ?? null) === $fingerprint, ARRAY_FILTER_USE_BOTH),
            [...$failedSources, ...array_keys(array_diff_assoc($after, $before))],
            $refreshFailures,
        );

        // A partial run must not look like a reconciliation: the page warns while the failure is the newest
        $this->settings->set(
            $catalogComplete && $failedSources === [] ? self::LAST_SYNCED_KEY : self::LAST_SYNC_FAILED_KEY,
            CarbonImmutable::now('UTC')->toIso8601String(),
        );

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
        return $this->instant(self::LAST_SYNCED_KEY);
    }

    public function lastSyncFailedAt(): ?DateTimeImmutable
    {
        return $this->instant(self::LAST_SYNC_FAILED_KEY);
    }

    private function instant(string $key): ?DateTimeImmutable
    {
        $value = $this->settings->get($key);

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
                /** @var array<string, bool> $seen whether each usage seen so far is hidden */
                $seen = [];
                $pending = [];

                foreach ($source->usages() as $usage) {
                    $row = $this->writer->usageRow($usage, $type, $module);

                    if ($row === null) {
                        continue;
                    }

                    $key = MediaIndexWriter::usageKey($row['file_id'], $row['record_id'], $row['role']);
                    // A usage given twice is hidden when any copy is, even one already written in an earlier chunk
                    $row['is_hidden'] = $row['is_hidden'] || ($seen[$key] ?? false);
                    $pending[$key] = $row;
                    $seen[$key] = $row['is_hidden'];

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
     * @param  array<string, bool>  $seen
     */
    private function deleteUnseen(string $type, array $seen): void
    {
        $stale = [];
        $rows = MediaUsageModel::query()
            ->where('source_type', $type)
            ->select(['id', 'file_id', 'record_id', 'role'])
            ->lazyById(MediaIndexWriter::CHUNK);

        foreach ($rows as $row) {
            if (array_key_exists(MediaIndexWriter::usageKey($row->file_id, $row->record_id, $row->role), $seen)) {
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
