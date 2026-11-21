<?php

declare(strict_types=1);

namespace App\Application\Activity\DTOs;

use App\Domain\Activity\ActivityIdentifierRule;
use InvalidArgumentException;

/**
 * How a subject is shown to the person viewing (core#774): its title and, optionally, a link to it
 * (a path of the site or an https address; the core drops any other link and keeps the title).
 */
final readonly class ActivitySubjectDTO
{
    /** Kept in lower case when it is a UUID */
    public string $subjectId;

    /**
     * @throws InvalidArgumentException when the id is not stable or the title is blank
     */
    public function __construct(
        string $subjectId,
        public string $title,
        public ?string $url = null,
    ) {
        $normalized = ActivityIdentifierRule::stableId($subjectId);

        if ($normalized === null) {
            throw new InvalidArgumentException('A described activity subject needs a stable id.');
        }

        $this->subjectId = $subjectId = $normalized;

        if (trim($title) === '') {
            throw new InvalidArgumentException("Described activity subject '{$subjectId}' needs a title.");
        }
    }
}
