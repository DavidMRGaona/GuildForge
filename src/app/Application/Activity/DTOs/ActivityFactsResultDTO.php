<?php

declare(strict_types=1);

namespace App\Application\Activity\DTOs;

use App\Application\Activity\Enums\ActivityReadOutcome;

/**
 * The facts of one subject as the core read them (core#774). When read, `$facts` is the whole truth
 * (empty: the subject no longer counts); otherwise it is empty and says nothing: keep what you had.
 */
final readonly class ActivityFactsResultDTO
{
    /**
     * @param  list<ActivityFactDTO>  $facts
     */
    private function __construct(
        public ActivityReadOutcome $outcome,
        public array $facts,
    ) {}

    /**
     * @param  list<ActivityFactDTO>  $facts
     */
    public static function read(array $facts): self
    {
        return new self(ActivityReadOutcome::Read, $facts);
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
}
