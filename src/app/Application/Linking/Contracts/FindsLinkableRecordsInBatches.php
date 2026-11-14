<?php

declare(strict_types=1);

namespace App\Application\Linking\Contracts;

use App\Application\Linking\DTOs\LinkableRecordDTO;

/**
 * Optional for a linkable record source: answers many records of its type for one viewer in one
 * call (one query), so a consumer showing a list of links does not ask one record at a time.
 *
 * LinkableRecordLookupInterface::findMany() uses it when the source implements it, and otherwise
 * calls find() once per id, so a source without it keeps working unchanged. The same visibility
 * rule as find() applies to every record.
 */
interface FindsLinkableRecordsInBatches extends LinkableRecordSourceInterface
{
    /** Most ids a source is asked at once; the lookup splits longer lists. */
    public const int MAX_IDS = 100;

    /**
     * The records among $ids the viewer may see, in any order. A missing or hidden record is
     * simply left out, as find() would answer null for it. Every DTO's type equals type() and its
     * id is one of $ids.
     *
     * @param  non-empty-list<string>  $ids  Distinct ids, at most MAX_IDS
     * @return list<LinkableRecordDTO>
     */
    public function findMany(array $ids, string $viewerId): array;
}
