<?php

declare(strict_types=1);

namespace Tests\Support\Surveys;

use RuntimeException;

/**
 * A survey subject source that fails when asked for its type.
 */
final class ThrowingTypeSurveySubjectSource extends FakeSurveySubjectSource
{
    public function sourceType(): string
    {
        throw new RuntimeException('Source type unavailable');
    }
}
