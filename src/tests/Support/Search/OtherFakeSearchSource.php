<?php

declare(strict_types=1);

namespace Tests\Support\Search;

final class OtherFakeSearchSource extends FakeSearchSource
{
    public function key(): string
    {
        return 'other';
    }
}
