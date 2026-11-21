<?php

declare(strict_types=1);

namespace App\Application\Activity\Services;

/**
 * How a module says the facts of one of its subjects may have changed (core#774): call it wherever a
 * subject ends, changes who took part, is hidden, deleted or stops counting, inside the change's
 * transaction or after it. ActivityRecorded is dispatched once the transaction commits (at once
 * outside one), never when it rolls back, and never while an impersonation lasts; a subject
 * published several times in one transaction is announced once, with a UUID id in lower case. It
 * never throws: an invalid type or id is dropped with a warning, and a listener's failure never
 * undoes the change.
 */
interface ActivityPublisherInterface
{
    public function recorded(string $sourceType, string $subjectId): void;
}
