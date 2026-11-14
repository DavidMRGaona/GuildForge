<?php

declare(strict_types=1);

namespace Tests\Support\Linking;

use App\Application\Linking\DTOs\LinkableRecordDTO;
use RuntimeException;

/**
 * A source whose records cannot be read right now (its tables are gone, a query fails…).
 */
final class ThrowingLinkableRecordSource extends FakeLinkableRecordSource
{
    public function type(): string
    {
        return 'throwing';
    }

    public function label(): string
    {
        throw new RuntimeException('Label unavailable');
    }

    public function search(string $query, string $viewerId, int $limit): array
    {
        throw new RuntimeException('Search unavailable');
    }

    public function find(string $id, string $viewerId): ?LinkableRecordDTO
    {
        throw new RuntimeException('Record unavailable');
    }
}
