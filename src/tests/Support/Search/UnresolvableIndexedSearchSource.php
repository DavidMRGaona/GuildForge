<?php

declare(strict_types=1);

namespace Tests\Support\Search;

use RuntimeException;

final class UnresolvableIndexedSearchSource extends FakeIndexedSearchSource
{
    public function __construct()
    {
        throw new RuntimeException('Cannot be built');
    }
}
