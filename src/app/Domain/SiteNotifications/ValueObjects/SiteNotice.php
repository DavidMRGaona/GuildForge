<?php

declare(strict_types=1);

namespace App\Domain\SiteNotifications\ValueObjects;

use Closure;

/**
 * A notice on its way to someone's notification centre, already checked and cleaned.
 */
final readonly class SiteNotice
{
    /**
     * @param  (Closure(int $count, int $otherActors): string)|null  $groupedTitle  Title of a group row
     *                                                                              holding $count notices.
     */
    public function __construct(
        public string $category,
        public string $title,
        public string $url,
        public ?string $body = null,
        public ?string $icon = null,
        public ?string $groupKey = null,
        public ?string $actorId = null,
        public ?string $subjectType = null,
        public ?string $subjectId = null,
        public ?Closure $groupedTitle = null,
    ) {}

    public function titleFor(int $count, int $otherActors): string
    {
        return $this->groupedTitle instanceof Closure ? ($this->groupedTitle)($count, $otherActors) : $this->title;
    }
}
