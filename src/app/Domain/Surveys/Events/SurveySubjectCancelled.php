<?php

declare(strict_types=1);

namespace App\Domain\Surveys\Events;

use DateTimeImmutable;

/**
 * A survey subject that will never end: cancelled, or deleted before it ended. Modules dispatch
 * it next to their own event, after the change commits; the surveys module drops the subject's
 * surveys that have not opened yet (one prepared to go out on its end would wait forever).
 */
final readonly class SurveySubjectCancelled
{
    public function __construct(
        public string $sourceType,
        public string $subjectId,
        public DateTimeImmutable $cancelledAt,
    ) {}
}
