<?php

declare(strict_types=1);

namespace Tests\Support\Media;

use RuntimeException;

final class UnresolvableMediaSource extends FakeMediaSource
{
    public function __construct()
    {
        throw new RuntimeException('Cannot be built');
    }
}
