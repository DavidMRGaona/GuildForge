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
    /**
     * @throws InvalidArgumentException when the id is not stable or the title is blank
     */
    public function __construct(
        public string $subjectId,
        public string $title,
        public ?string $url = null,
    ) {
        if (! ActivityIdentifierRule::isStableId($subjectId)) {
            throw new InvalidArgumentException('A described activity subject needs a stable id.');
        }

        if (trim($title) === '') {
            throw new InvalidArgumentException("Described activity subject '{$subjectId}' needs a title.");
        }
    }
}
