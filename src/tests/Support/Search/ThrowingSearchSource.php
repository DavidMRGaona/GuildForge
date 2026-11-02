<?php

declare(strict_types=1);

namespace Tests\Support\Search;

use RuntimeException;

final class ThrowingSearchSource extends FakeSearchSource
{
    public function key(): string
    {
        return 'throwing';
    }

    public function search(string $query, ?object $user, int $limit): array
    {
        throw new RuntimeException('Search backend is down');
    }
}
