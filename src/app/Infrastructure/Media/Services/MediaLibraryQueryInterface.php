<?php

declare(strict_types=1);

namespace App\Infrastructure\Media\Services;

use App\Domain\Media\Enums\MediaFileStatus;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaFileModel;
use Illuminate\Database\Eloquent\Builder;

/**
 * What the panel media library lists. It hands out the Eloquent query, because the panel table pages, sorts,
 * searches and filters it, so it lives in Infrastructure beside the model and only the panel uses it.
 * Statuses follow MediaFileStatusRule, in SQL and per file, and are only for display: deleting goes through
 * MediaDeletionServiceInterface, which checks everything again.
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

    /**
     * Narrows the query to the files with the search in the title of a use or in the folder.
     *
     * @param  Builder<MediaFileModel>  $query
     * @return Builder<MediaFileModel>
     */
    public function applySearch(Builder $query, string $search): Builder;

    /**
     * Narrows the query to the images a form may reuse: their folder is claimed by an active, visible source
     * that releases files through the core (ReleasesMediaThroughCoreInterface), and they have a format.
     *
     * @param  Builder<MediaFileModel>  $query
     * @return Builder<MediaFileModel>
     */
    public function applyReusable(Builder $query): Builder;

    /**
     * Whether files stored in the folder (with or without a trailing slash) could be reused, by the same rule.
     */
    public function isReusableFolder(string $folder): bool;

    public function statusOf(MediaFileModel $file): MediaFileStatus;

    public function stats(): MediaLibraryStatsDTO;
}
