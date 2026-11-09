<?php

declare(strict_types=1);

namespace Tests\Support\Publications;

use RuntimeException;

final class ThrowingTypePublicationSource extends FakePublicationSource
{
    public function type(): string
    {
        throw new RuntimeException('Type unavailable');
    }
}
