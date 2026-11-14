<?php

declare(strict_types=1);

namespace Tests\Support\Linking;

use RuntimeException;

/**
 * A batch source whose records cannot be read right now.
 */
final class ThrowingBatchLinkableRecordSource extends BatchFakeLinkableRecordSource
{
    public function type(): string
    {
        return 'throwing-batch';
    }

    public function findMany(array $ids, string $viewerId): array
    {
        throw new RuntimeException('Records unavailable');
    }
}
