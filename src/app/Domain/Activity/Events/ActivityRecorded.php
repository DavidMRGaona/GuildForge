<?php

declare(strict_types=1);

namespace App\Domain\Activity\Events;

/**
 * «The facts of this subject may have changed» (core#774): it ended, somebody joined or left it, it
 * was hidden, deleted or stopped counting. It carries ids only; a listener asks the source, through
 * ActivityReaderInterface, what is true now. Modules never dispatch it themselves: they call
 * ActivityPublisherInterface::recorded(), which dispatches it once the change commits.
 */
final readonly class ActivityRecorded
{
    public function __construct(
        public string $sourceType,
        public string $subjectId,
    ) {}
}
