<?php

declare(strict_types=1);

namespace App\Infrastructure\Media\Services;

use App\Application\Media\Contracts\MediaUsageSourceInterface;
use App\Application\Media\Services\MediaIndexerInterface;
use App\Application\Media\Services\MediaSourceRegistryInterface;
use App\Application\Services\TimezoneServiceInterface;
use App\Domain\Media\Enums\MediaFileStatus;
use App\Domain\Media\Exceptions\InvalidMediaFileIdException;
use App\Domain\Media\MediaFileStatusRule;
use App\Domain\Media\ValueObjects\MediaFileId;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaFileModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * The media library in SQL. Which source claims a file is decided as MediaSourceRegistry::claimingSource()
 * does (longest claimed folder, minus that source's excluded subfolders, first registered on a tie), with
 * prefixes compared by substr() rather than LIKE: folders hold underscores, and LIKE ignores case on SQLite.
 */
final readonly class EloquentMediaLibraryQuery implements MediaLibraryQueryInterface
{
    public function __construct(
        private MediaSourceRegistryInterface $registry,
        private MediaIndexerInterface $indexer,
        private TimezoneServiceInterface $timezone,
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

    public function statusOf(MediaFileModel $file): MediaFileStatus
    {
        return MediaFileStatusRule::for(
            hasUsages: $this->hasUsages($file),
            claimedByActiveSource: $this->isClaimed($file->file_id),
            uploadedAt: $file->uploaded_at->toImmutable(),
            now: CarbonImmutable::now(),
        );
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
        try {
            $claimer = $this->registry->claimingSource(new MediaFileId($fileId));

            return $claimer instanceof MediaUsageSourceInterface && ! $claimer->hidden();
        } catch (InvalidMediaFileIdException) {
            return false;
        } catch (Throwable) {
            // A source that cannot say whether it is hidden claims nothing here
            return false;
        }
    }

    /**
     * Every claimed folder of the active sources, in registration order. A source whose folders or
     * excluded subfolders cannot be read claims nothing, as in the registry.
     *
     * @return list<array{type: string, hidden: bool, folder: string, excluded: list<string>}>
     */
    private function claims(): array
    {
        $claims = [];

        foreach ($this->registry->sources() as $source) {
            try {
                $type = $source->type();
                $hidden = $source->hidden();
                $folders = $source->folders();
                $excluded = array_values(array_filter($source->excludedSubfolders(), static fn (string $subfolder): bool => $subfolder !== ''));
            } catch (Throwable) {
                continue;
            }

            foreach ($folders as $folder) {
                if ($folder !== '') {
                    $claims[] = ['type' => $type, 'hidden' => $hidden, 'folder' => $folder, 'excluded' => $excluded];
                }
            }
        }

        return $claims;
    }

    /**
     * @param  list<array{type: string, hidden: bool, folder: string, excluded: list<string>}>  $claims
     * @return array<int, array{type: string, hidden: bool, folder: string, excluded: list<string>}>
     */
    private function visible(array $claims): array
    {
        return array_filter($claims, static fn (array $claim): bool => ! $claim['hidden']);
    }

    /**
     * Files claimed through any of the given claims (or, negated, through none of them).
     *
     * @param  Builder<MediaFileModel>  $query
     * @param  array<int, array{type: string, hidden: bool, folder: string, excluded: list<string>}>  $selected  keyed by position in $claims
     * @param  list<array{type: string, hidden: bool, folder: string, excluded: list<string>}>  $claims
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
     * @param  array{type: string, hidden: bool, folder: string, excluded: list<string>}  $other
     * @param  array{type: string, hidden: bool, folder: string, excluded: list<string>}  $claim
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
     * @param  array{type: string, hidden: bool, folder: string, excluded: list<string>}  $claim
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
