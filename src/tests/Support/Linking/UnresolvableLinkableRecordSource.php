<?php

declare(strict_types=1);

namespace Tests\Support\Linking;

use Countable;

/**
 * A linkable record source the container cannot build, as when a module's source depends on a
 * binding that is missing.
 */
final class UnresolvableLinkableRecordSource extends FakeLinkableRecordSource
{
    public function __construct(
        public readonly Countable $unboundDependency,
    ) {}
}
