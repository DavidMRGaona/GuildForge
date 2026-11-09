<?php

declare(strict_types=1);

namespace Tests\Support\Publications;

use Countable;

/**
 * A publication source the container cannot build, as when a module's source depends on a
 * binding that is missing.
 */
final class UnresolvablePublicationSource extends FakePublicationSource
{
    public function __construct(
        public readonly Countable $unboundDependency,
    ) {}
}
