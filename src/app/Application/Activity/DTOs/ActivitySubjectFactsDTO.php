<?php

declare(strict_types=1);

namespace App\Application\Activity\DTOs;

use App\Domain\Activity\ActivityIdentifierRule;
use InvalidArgumentException;

/**
 * Everything that is true now about one subject (core#774): its facts, one per person and role.
 * No facts means it no longer counts (it was hidden, deleted, or never ended). The core wraps what
 * a source's facts() returns in it, so a source that breaks these rules is read as failed.
 */
final readonly class ActivitySubjectFactsDTO
{
    /**
     * @param  list<ActivityFactDTO>  $facts
     *
     * @throws InvalidArgumentException when the id is not stable, the facts are not a list of facts
     *                                  or the same person holds the same role twice
     */
    public function __construct(
        public string $subjectId,
        public array $facts,
    ) {
        if (! ActivityIdentifierRule::isStableId($subjectId)) {
            throw new InvalidArgumentException('An activity subject needs a stable id (letters, digits, ".", "_", ":", "-").');
        }

        if (! ActivityContractChecks::isListOf($facts, ActivityFactDTO::class)) {
            throw new InvalidArgumentException("The facts of activity subject '{$subjectId}' must be a list of ActivityFactDTO.");
        }

        $seen = [];
        foreach ($facts as $fact) {
            $key = $fact->userId.'|'.$fact->role->value;
            if (isset($seen[$key])) {
                throw new InvalidArgumentException("Activity subject '{$subjectId}' holds the same person twice in role '{$fact->role->value}'.");
            }

            $seen[$key] = true;
        }
    }
}
