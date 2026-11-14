<?php

declare(strict_types=1);

namespace Tests\Support\Linking;

/**
 * A source that counts how many times the container builds it.
 */
final class CountingLinkableRecordSource extends FakeLinkableRecordSource
{
    public static int $built = 0;

    public function __construct()
    {
        self::$built++;
    }

    public function type(): string
    {
        return 'counting';
    }
}
