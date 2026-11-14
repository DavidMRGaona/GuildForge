<?php

declare(strict_types=1);

namespace Tests\Support\Linking;

use RuntimeException;

final class ThrowingTypeLinkableRecordSource extends FakeLinkableRecordSource
{
    public function type(): string
    {
        throw new RuntimeException('Type unavailable');
    }
}
