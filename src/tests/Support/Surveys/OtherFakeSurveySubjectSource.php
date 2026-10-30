<?php

declare(strict_types=1);

namespace Tests\Support\Surveys;

/**
 * A second survey subject source with its own type.
 */
final class OtherFakeSurveySubjectSource extends FakeSurveySubjectSource
{
    public function sourceType(): string
    {
        return 'other-fake';
    }
}
