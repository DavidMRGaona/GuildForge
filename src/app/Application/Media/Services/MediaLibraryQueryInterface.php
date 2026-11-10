<?php

declare(strict_types=1);

namespace App\Application\Media\Services;

use App\Application\Media\DTOs\MediaLibraryStatsDTO;
use App\Domain\Media\Enums\MediaFileStatus;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaFileModel;
use Illuminate\Database\Eloquent\Builder;

/**
 * What the panel media library lists. It works on the Eloquent query because the panel table pages, sorts,
 * searches and filters it (as UserModelQueryServiceInterface, a bridge to the framework). Statuses follow
 * MediaFileStatusRule, in SQL and per file, and are only for display: deleting checks everything again.
 */
interface MediaLibraryQueryInterface
{
    /**
     * Indexed files outside the folders of hidden sources, with `usages_count`.
     *
     * @return Builder<MediaFileModel>
     */
    public function query(): Builder;

    /**
     * Narrows the query to the files with the given status, by the same rule as MediaFileStatusRule:
     * the folders of the active sources and a grace period ending now.
     *
     * @param  Builder<MediaFileModel>  $query
     * @return Builder<MediaFileModel>
     */
    public function applyStatus(Builder $query, MediaFileStatus $status): Builder;

    /**
     * Narrows the query to the files whose folder the source of the given type claims, or, with null, to
     * those no active source claims.
     *
     * @param  Builder<MediaFileModel>  $query
     * @return Builder<MediaFileModel>
     */
    public function applyOrigin(Builder $query, ?string $type): Builder;

    public function statusOf(MediaFileModel $file): MediaFileStatus;

    public function stats(): MediaLibraryStatsDTO;
}
