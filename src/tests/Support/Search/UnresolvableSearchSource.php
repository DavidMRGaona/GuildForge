<?php

declare(strict_types=1);

namespace Tests\Support\Search;

use RuntimeException;

final class UnresolvableSearchSource extends FakeSearchSource
{
    public function __construct()
    {
        throw new RuntimeException('Cannot be built');
    }
}
