<?php

declare(strict_types=1);

namespace App\Infrastructure\Media\Services;

use App\Application\Media\Contracts\ConcealsUnusedMediaFiles;
use App\Application\Media\Contracts\MediaUsageSourceInterface;
use App\Application\Media\Contracts\ReleasesMediaThroughCoreInterface;
use App\Application\Media\Contracts\ShowsMediaPreviews;
use App\Application\Media\Services\MediaIndexerInterface;
use App\Application\Media\Services\MediaSourceRegistryInterface;
use App\Application\Services\TimezoneServiceInterface;
use App\Domain\Media\Enums\MediaFileStatus;
use App\Domain\Media\MediaFileStatusRule;
use App\Domain\Media\ValueObjects\MediaFileId;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaFileModel;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaUsageModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * The media library in SQL. Which source claims a file is decided as MediaSourceRegistry::claimingSource()
 * does (longest claimed folder, minus that source's excluded subfolders, first registered on a tie), with
 * prefixes compared by substr() rather than LIKE: folders hold underscores, and LIKE ignores case on SQLite.
 *
 * Whether a file shows its image is worked out on reading (core#837), from the sources that claim it now and
 * the last sync of each (MediaSourceSyncState), rather than stored in the index: a module enabled, disabled
 * or updated changes it at once, and a source whose rows may be stale shows nothing until it is synced.
 *
 * Unused files of a folder whose sources show their previews are shown, since they are what gets reviewed
 * before deleting, unless a visible source claiming them conceals its unused files (core#843).
 *
 * Claims are read once per instance: the panel keeps one instance while it draws a page.
 *
 * @phpstan-type Claim array{type: string, hidden: bool, releasesThroughCore: bool, showsPreviews: bool, concealsUnused: bool, folder: string, excluded: list<string>}
 */
final class EloquentMediaLibraryQuery implements MediaLibraryQueryInterface
{
    /** @var list<Claim>|null */
    private ?array $claims = null;

    /** @var list<string>|null types of the active sources the index is up to date with */
    private ?array $upToDate = null;

    public function __construct(
        private readonly MediaSourceRegistryInterface $registry,
        private readonly MediaIndexerInterface $indexer,
        private readonly TimezoneServiceInterface $timezone,
        private readonly MediaSourceSyncState $syncState,
    ) {}

    public function query(): Builder
    {
        return $this->files()->withCount('usages');
    }

    public function applyStatus(Builder $query, MediaFileStatus $status): Builder
    {
        if ($status === MediaFileStatus::InUse) {
            return $query->whereHas('usages');
        }

        $query->whereDoesntHave('usages');
        $claims = $this->claims();
        $visible = $this->visible($claims);

        if ($status === MediaFileStatus::UnknownOrigin) {
            return $this->whereClaimed($query, $visible, $claims, negate: true);
        }

        $this->whereClaimed($query, $visible, $claims, negate: false);
        // Same bound as the rule: the grace period of a file uploaded exactly that long ago is over
        $graceStart = $this->timezone->toStorage(CarbonImmutable::now())
            ->modify(sprintf('-%d days', MediaFileStatusRule::GRACE_DAYS))
            ->format('Y-m-d H:i:s');

        return $query->where('media_files.uploaded_at', $status === MediaFileStatus::Recent ? '>' : '<=', $graceStart);
    }

    public function applyOrigin(Builder $query, ?string $type): Builder
    {
        $claims = $this->claims();
        $visible = $this->visible($claims);

        if ($type === null) {
            return $this->whereClaimed($query, $visible, $claims, negate: true);
        }

        $ofType = array_filter($visible, static fn (array $claim): bool => $claim['type'] === $type);

        return $this->whereClaimed($query, $ofType, $claims, negate: false);
    }

    public function applySearch(Builder $query, string $search): Builder
    {
        return $query->where(
            fn (Builder $matches): Builder => $matches
                ->whereHas('usages', fn (Builder $usages): Builder => $usages->whereLike('title', '%'.$search.'%'))
                ->orWhereLike('media_files.folder', '%'.$search.'%'),
        );
    }

    public function applyReusable(Builder $query): Builder
    {
        $claims = $this->claims();
        $visible = $this->visible($claims);
        // The picker shows thumbnails: only images the library would show are offered (core#837)
        $reusable = array_filter($visible, static fn (array $claim): bool => $claim['releasesThroughCore'] && $claim['showsPreviews']);
        $concealing = array_filter($visible, static fn (array $claim): bool => ! $claim['showsPreviews']);
        $concealingUnused = array_filter($visible, static fn (array $claim): bool => $claim['concealsUnused']);

        $query->where('media_files.resource_type', 'image')
            ->whereNotNull('media_files.format')
            // An image a usage keeps out of sight, or one with a usage the index may hold stale, is never offered
            ->whereDoesntHave('usages', fn (Builder $usages): Builder => $this->whereConcealing($usages));

        foreach ($concealing as $claim) {
            // Nor one that any visible source claiming it would not show, winning the folder or not
            $query->whereNot(fn (Builder $claimed): Builder => $this->whereMatches($claimed, $claim));
        }

        foreach ($concealingUnused as $claim) {
            // Nor an unused one that any visible source claiming it keeps out of sight (core#843)
            $query->whereNot(fn (Builder $claimed): Builder => $this->whereMatches($claimed, $claim)->whereDoesntHave('usages'));
        }

        return $this->whereClaimed($query, $reusable, $claims, negate: false);
    }

    public function isReusableFolder(string $folder): bool
    {
        try {
            $folder = trim($folder, '/');
            $claimer = $this->registry->claimingSource(new MediaFileId($folder === '' ? 'probe' : $folder.'/probe'));

            return $claimer instanceof ReleasesMediaThroughCoreInterface && ! $claimer->hidden();
        } catch (Throwable) {
            // An odd folder, or a source that cannot say whether it is hidden: nothing is reused
            return false;
        }
    }

    public function statusOf(MediaFileModel $file): MediaFileStatus
    {
        return MediaFileStatusRule::for(
            hasUsages: $this->hasUsages($file),
            claimedByActiveSource: $this->isClaimed($file->file_id),
            uploadedAt: $file->uploaded_at->toImmutable(),
            now: CarbonImmutable::now(),
        );
    }

    public function conceals(MediaFileModel $file): bool
    {
        if (! $this->visibleClaimer($file->file_id) instanceof ShowsMediaPreviews) {
            return true;
        }

        // Every visible source that claims the file must show it, not only the one that wins its folder
        $claiming = array_filter(
            $this->visible($this->claims()),
            fn (array $claim): bool => $this->claimsFile($claim, $file->file_id),
        );

        if (array_any($claiming, static fn (array $claim): bool => ! $claim['showsPreviews'])) {
            return true;
        }

        // An unused file may be private where any of them says so (core#843)
        if (array_any($claiming, static fn (array $claim): bool => $claim['concealsUnused']) && ! $this->hasUsages($file)) {
            return true;
        }

        return $this->hasConcealingUsage($file);
    }

    public function stats(): MediaLibraryStatsDTO
    {
        $unused = $this->applyStatus($this->files(), MediaFileStatus::Unused);

        return new MediaLibraryStatsDTO(
            files: $this->files()->count(),
            bytes: (int) $this->files()->sum('bytes'),
            unusedFiles: (clone $unused)->count(),
            unusedBytes: (int) $unused->sum('bytes'),
            lastSyncedAt: $this->indexer->lastSyncedAt(),
            lastSyncFailedAt: $this->indexer->lastSyncFailedAt(),
        );
    }

    /**
     * @return Builder<MediaFileModel>
     */
    private function files(): Builder
    {
        $query = MediaFileModel::query();

        foreach ($this->registry->hiddenFolders() as $folder) {
            if ($folder !== '') {
                $this->whereStartsWith($query, $folder, negate: true);
            }
        }

        return $query;
    }

    private function hasUsages(MediaFileModel $file): bool
    {
        if ($file->relationLoaded('usages')) {
            return $file->usages->isNotEmpty();
        }

        $count = $file->getAttribute('usages_count');

        return is_numeric($count) ? (int) $count > 0 : $file->usages()->exists();
    }

    private function isClaimed(string $fileId): bool
    {
        return $this->visibleClaimer($fileId) instanceof MediaUsageSourceInterface;
    }

    /**
     * The visible source that wins the file's folder, or null: none claims it, it is hidden, the id is odd or
     * the source cannot say whether it is hidden (it claims nothing here then).
     */
    private function visibleClaimer(string $fileId): ?MediaUsageSourceInterface
    {
        try {
            $claimer = $this->registry->claimingSource(new MediaFileId($fileId));

            return $claimer instanceof MediaUsageSourceInterface && ! $claimer->hidden() ? $claimer : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Whether a usage keeps the file out of sight: it is hidden (core#830), or its source is not one the index
     * is up to date with (core#837), so its `is_hidden` may be stale. A source with no folder (rich text) or of
     * a disabled module counts too.
     */
    private function hasConcealingUsage(MediaFileModel $file): bool
    {
        $upToDate = $this->upToDate();

        if ($file->relationLoaded('usages')) {
            return $file->usages->contains(static fn (MediaUsageModel $usage): bool => $usage->is_hidden
                || ! in_array($usage->source_type, $upToDate, true));
        }

        return $file->usages()->where(fn (Builder $usages): Builder => $this->whereConcealing($usages))->exists();
    }

    /**
     * The usages that keep a file out of sight, in SQL; the same rule as hasConcealingUsage().
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $usages
     * @return Builder<TModel>
     */
    private function whereConcealing(Builder $usages): Builder
    {
        return $usages->where(fn (Builder $concealing): Builder => $concealing
            ->where('is_hidden', true)
            ->orWhereNotIn('source_type', $this->upToDate()));
    }

    /**
     * @return list<string>
     */
    private function upToDate(): array
    {
        return $this->upToDate ??= $this->syncState->upToDate($this->registry->sources());
    }

    /**
     * The same test as whereMatches(), in PHP: case sensitive, by prefix, minus the excluded subfolders.
     *
     * @param  Claim  $claim
     */
    private function claimsFile(array $claim, string $fileId): bool
    {
        if (! str_starts_with($fileId, $claim['folder'])) {
            return false;
        }

        $rest = substr($fileId, strlen($claim['folder']));

        return ! array_any($claim['excluded'], static fn (string $subfolder): bool => str_starts_with($rest, $subfolder));
    }

    /**
     * Every claimed folder of the active sources, in registration order. A source whose folders or
     * excluded subfolders cannot be read claims nothing, as in the registry. Whether it is hidden is the
     * registry's answer, read once and failing closed, so a later call that throws never drops a claim and
     * what it conceals. A source shows its previews only when it declares ShowsMediaPreviews and the index is
     * up to date with it as it is now.
     *
     * @return list<Claim>
     */
    private function claims(): array
    {
        if ($this->claims !== null) {
            return $this->claims;
        }

        $claims = [];
        $upToDate = $this->upToDate();

        foreach ($this->registry->sources() as $source) {
            try {
                $type = $source->type();
                $folders = $source->folders();
                $excluded = array_values(array_filter($source->excludedSubfolders(), static fn (string $subfolder): bool => $subfolder !== ''));
            } catch (Throwable) {
                continue;
            }

            $hidden = $this->registry->isHidden($type);

            foreach ($folders as $folder) {
                if ($folder !== '') {
                    $claims[] = [
                        'type' => $type,
                        'hidden' => $hidden,
                        'releasesThroughCore' => $source instanceof ReleasesMediaThroughCoreInterface,
                        'showsPreviews' => $source instanceof ShowsMediaPreviews && in_array($type, $upToDate, true),
                        'concealsUnused' => $source instanceof ConcealsUnusedMediaFiles,
                        'folder' => $folder,
                        'excluded' => $excluded,
                    ];
                }
            }
        }

        return $this->claims = $claims;
    }

    /**
     * @param  list<Claim>  $claims
     * @return array<int, Claim>
     */
    private function visible(array $claims): array
    {
        return array_filter($claims, static fn (array $claim): bool => ! $claim['hidden']);
    }

    /**
     * Files claimed through any of the given claims (or, negated, through none of them).
     *
     * @param  Builder<MediaFileModel>  $query
     * @param  array<int, Claim>  $selected  keyed by position in $claims
     * @param  list<Claim>  $claims
     * @return Builder<MediaFileModel>
     */
    private function whereClaimed(Builder $query, array $selected, array $claims, bool $negate): Builder
    {
        if ($selected === []) {
            return $negate ? $query : $query->whereRaw('1 = 0');
        }

        $any = function (Builder $any) use ($selected, $claims): void {
            foreach ($selected as $position => $claim) {
                $any->orWhere(function (Builder $won) use ($position, $claim, $claims): void {
                    $this->whereMatches($won, $claim);

                    // A claim that would win over this one for the same file
                    foreach ($claims as $otherPosition => $other) {
                        if ($this->beats($other, $otherPosition, $claim, $position)) {
                            $won->whereNot(fn (Builder $beaten): Builder => $this->whereMatches($beaten, $other));
                        }
                    }
                });
            }
        };

        return $negate ? $query->whereNot($any) : $query->where($any);
    }

    /**
     * @param  Claim  $other
     * @param  Claim  $claim
     */
    private function beats(array $other, int $otherPosition, array $claim, int $position): bool
    {
        if ($otherPosition === $position || ! str_starts_with($other['folder'], $claim['folder'])) {
            return false;
        }

        // A longer folder wins; the same folder goes to whoever registered first
        return strlen($other['folder']) > strlen($claim['folder']) || $otherPosition < $position;
    }

    /**
     * @param  Builder<MediaFileModel>  $query
     * @param  Claim  $claim
     * @return Builder<MediaFileModel>
     */
    private function whereMatches(Builder $query, array $claim): Builder
    {
        $this->whereStartsWith($query, $claim['folder']);

        foreach ($claim['excluded'] as $subfolder) {
            $this->whereStartsWith($query, $claim['folder'].$subfolder, negate: true);
        }

        return $query;
    }

    /**
     * Case-sensitive prefix test on both PostgreSQL and SQLite; both count characters, not bytes. The length
     * is cast so PostgreSQL does not have to guess the type of the bound value.
     *
     * @param  Builder<MediaFileModel>  $query
     */
    private function whereStartsWith(Builder $query, string $prefix, bool $negate = false): void
    {
        $query->whereRaw(
            $negate
                ? 'substr(media_files.file_id, 1, CAST(? AS INTEGER)) <> ?'
                : 'substr(media_files.file_id, 1, CAST(? AS INTEGER)) = ?',
            [mb_strlen($prefix), $prefix],
        );
    }
}
