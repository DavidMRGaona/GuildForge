<?php

declare(strict_types=1);

namespace Tests\Support\Surveys;

/**
 * A survey subject source whose type is not kebab-case.
 */
final class InvalidTypeSurveySubjectSource extends FakeSurveySubjectSource
{
    public function sourceType(): string
    {
        return 'Game Table';
    }
}
