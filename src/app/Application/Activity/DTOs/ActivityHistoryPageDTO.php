<?php

declare(strict_types=1);

namespace App\Application\Activity\DTOs;

use App\Application\Activity\ActivityContractChecks;
use InvalidArgumentException;

/**
 * One page of everything a source counts (core#774), for recalculating from scratch: each subject
 * once, with its facts, and the cursor of the next page (null on the last one). The cursor is opaque
 * to the caller: whatever the source needs to go on (an encoded last id, say), never an offset that
 * a new row would shift.
 */
final readonly class ActivityHistoryPageDTO
{
    /** The most subjects the core asks for in one page */
    public const int MAX_LIMIT = 500;

    public const int MAX_CURSOR_LENGTH = 1024;

    /**
     * @param  list<ActivitySubjectFactsDTO>  $subjects
     *
     * @throws InvalidArgumentException when a subject is not ActivitySubjectFactsDTO or comes twice, or the cursor is empty or too long
     */
    public function __construct(
        public array $subjects,
        public ?string $nextCursor,
    ) {
        if (! ActivityContractChecks::isListOf($subjects, ActivitySubjectFactsDTO::class)) {
            throw new InvalidArgumentException('An activity history page holds a list of ActivitySubjectFactsDTO.');
        }

        $seen = [];
        foreach ($subjects as $subject) {
            if (isset($seen[$subject->subjectId])) {
                throw new InvalidArgumentException("Activity subject '{$subject->subjectId}' comes twice in one history page.");
            }

            $seen[$subject->subjectId] = true;
        }

        if ($nextCursor !== null && ($nextCursor === '' || strlen($nextCursor) > self::MAX_CURSOR_LENGTH)) {
            throw new InvalidArgumentException(sprintf('An activity history cursor is null on the last page, otherwise 1 to %d bytes.', self::MAX_CURSOR_LENGTH));
        }
    }

    public function isLast(): bool
    {
        return $this->nextCursor === null;
    }
}
