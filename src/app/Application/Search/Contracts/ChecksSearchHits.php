<?php

declare(strict_types=1);

namespace App\Application\Search\Contracts;

/**
 * An indexed search source that checks its results against its own records when they are read.
 *
 * The stored documents are refreshed by a queued job, so for a moment after a write they may still show
 * something that is no longer visible (unpublished, hidden, moved somewhere more private). The site search
 * asks the source of every type that implements this, once per page with every id of that type, and keeps
 * only the ids it returns. It fails closed: when this throws, no result of the type is shown.
 */
interface ChecksSearchHits
{
    /**
     * The ids that the person may still see, read in one query: never one query per id.
     * Ids not asked for are ignored, so this can only take results away.
     *
     * @param  non-empty-list<string>  $ids  ids given by document() and documents(), of this source's type
     * @param  object|null  $user  the signed-in person, or null for a guest
     * @return list<string>
     */
    public function visibleSearchHits(array $ids, ?object $user): array;
}
