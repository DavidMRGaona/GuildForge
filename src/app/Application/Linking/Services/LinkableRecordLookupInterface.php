<?php

declare(strict_types=1);

namespace App\Application\Linking\Services;

use App\Application\Linking\DTOs\LinkableRecordDTO;

/**
 * What consumers of linkable records use instead of asking the sources themselves: it fails
 * closed in one place.
 *
 * A type without a source (its module disabled or uninstalled, a source that cannot be built)
 * and a source that throws or answers with something else than what was asked are treated
 * alike: the record is not shown. Only the second is an error and is logged; the first is
 * ordinary (a module was turned off) and logs nothing. Nothing here throws.
 */
interface LinkableRecordLookupInterface
{
    /** Default number of records a search returns. */
    public const int DEFAULT_SEARCH_LIMIT = 20;

    /** Most records a search may ask a source for. */
    public const int MAX_SEARCH_LIMIT = 50;

    /**
     * The record as the viewer may see it, or null when it does not exist, the viewer may not
     * see it, its type has no source or the source failed.
     */
    public function find(string $type, string $id, string $viewerId): ?LinkableRecordDTO;

    /**
     * The records of the type among $ids that the viewer may see, keyed by id, in the order
     * asked (repeated ids count once). An id that does not exist or is hidden is simply absent.
     *
     * A source implementing FindsLinkableRecordsInBatches is asked at most
     * FindsLinkableRecordsInBatches::MAX_IDS ids per call (longer lists are split); any other
     * source is asked once per id through find(). Empty when the type has no source or the
     * source failed for any of them; records answered for ids not asked, or of another type, are
     * dropped and logged.
     *
     * @param  list<string>  $ids
     * @return array<string, LinkableRecordDTO>
     */
    public function findMany(string $type, array $ids, string $viewerId): array;

    /**
     * Records of the type matching the query that the viewer may see, at most $limit (capped at
     * MAX_SEARCH_LIMIT; below 1 nothing). Empty when the type has no source or the source failed.
     *
     * @return list<LinkableRecordDTO>
     */
    public function search(string $type, string $query, string $viewerId, int $limit = self::DEFAULT_SEARCH_LIMIT): array;

    /**
     * Label of every type that can be linked right now, keyed by type, in registration order
     * (e.g. to fill a type picker). A source whose label cannot be read is left out and logged.
     *
     * @return array<string, string>
     */
    public function types(): array;
}
