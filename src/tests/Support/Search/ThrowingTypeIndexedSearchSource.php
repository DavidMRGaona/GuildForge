<?php

declare(strict_types=1);

namespace Tests\Support\Search;

use RuntimeException;

final class ThrowingTypeIndexedSearchSource extends FakeIndexedSearchSource
{
    public function type(): string
    {
        throw new RuntimeException('No type today');
    }
}
