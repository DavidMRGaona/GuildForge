<?php

declare(strict_types=1);

namespace App\Application\Activity\DTOs;

use App\Application\Activity\Enums\ActivityReadOutcome;

/**
 * One history page as the core read it (core#774): the page when read, null otherwise. A walk that
 * meets a page it could not read stops there; nothing it has not read is gone.
 */
final readonly class ActivityHistoryResultDTO
{
    private function __construct(
        public ActivityReadOutcome $outcome,
        public ?ActivityHistoryPageDTO $page,
    ) {}

    public static function read(ActivityHistoryPageDTO $page): self
    {
        return new self(ActivityReadOutcome::Read, $page);
    }

    public static function missing(): self
    {
        return new self(ActivityReadOutcome::Missing, null);
    }

    public static function failed(): self
    {
        return new self(ActivityReadOutcome::Failed, null);
    }

    public function isRead(): bool
    {
        return $this->outcome === ActivityReadOutcome::Read;
    }
}
