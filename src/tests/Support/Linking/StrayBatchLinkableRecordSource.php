<?php

declare(strict_types=1);

namespace Tests\Support\Linking;

/**
 * A batch source that answers with every record it holds, whatever was asked: records of other
 * ids and other types included.
 */
final class StrayBatchLinkableRecordSource extends BatchFakeLinkableRecordSource
{
    public function findMany(array $ids, string $viewerId): array
    {
        self::$batches[] = $ids;

        return self::$records;
    }
}
