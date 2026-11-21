<?php

declare(strict_types=1);

namespace App\Application\Activity\DTOs;

use App\Application\Activity\Enums\ActivityReadOutcome;

/**
 * Subjects described for the person viewing, as the core read them (core#774): only those asked
 * for that the viewer may see, in a list (numeric ids would turn into int keys in a map; use find()).
 * Not read: nothing to show.
 */
final readonly class ActivityDescriptionsResultDTO
{
    /**
     * @param  list<ActivitySubjectDTO>  $subjects
     */
    private function __construct(
        public ActivityReadOutcome $outcome,
        public array $subjects,
    ) {}

    /**
     * @param  list<ActivitySubjectDTO>  $subjects
     */
    public static function read(array $subjects): self
    {
        return new self(ActivityReadOutcome::Read, $subjects);
    }

    public static function missing(): self
    {
        return new self(ActivityReadOutcome::Missing, []);
    }

    public static function failed(): self
    {
        return new self(ActivityReadOutcome::Failed, []);
    }

    public function isRead(): bool
    {
        return $this->outcome === ActivityReadOutcome::Read;
    }

    public function find(string $subjectId): ?ActivitySubjectDTO
    {
        foreach ($this->subjects as $subject) {
            if ($subject->subjectId === $subjectId) {
                return $subject;
            }
        }

        return null;
    }
}
