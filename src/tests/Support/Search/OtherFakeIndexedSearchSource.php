<?php

declare(strict_types=1);

namespace Tests\Support\Search;

final class OtherFakeIndexedSearchSource extends FakeIndexedSearchSource
{
    public function type(): string
    {
        return 'other-indexed';
    }
}
