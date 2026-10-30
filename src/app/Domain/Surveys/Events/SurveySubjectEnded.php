<?php

declare(strict_types=1);

namespace App\Domain\Surveys\Events;

use DateTimeImmutable;

/**
 * A survey subject offered by a module (see SurveySubjectSourceInterface) has ended.
 *
 * The module that owns the subject dispatches it next to its own "ended" event, once the
 * change has committed, so the core never needs to know module names. The surveys module
 * listens to it to send or offer the subject's survey; without it, nothing happens.
 *
 * `$endedAt` is an instant: compare it as such, whatever its timezone.
 */
final readonly class SurveySubjectEnded
{
    public function __construct(
        public string $sourceType,
        public string $subjectId,
        public DateTimeImmutable $endedAt,
    ) {}
}
