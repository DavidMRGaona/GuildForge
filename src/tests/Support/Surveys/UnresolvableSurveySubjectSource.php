<?php

declare(strict_types=1);

namespace Tests\Support\Surveys;

use Countable;

/**
 * A survey subject source the container cannot build, as when a module's source
 * depends on a binding that is missing.
 */
final class UnresolvableSurveySubjectSource extends FakeSurveySubjectSource
{
    public function __construct(
        public readonly Countable $unboundDependency,
    ) {}
}
