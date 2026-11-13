<?php

declare(strict_types=1);

namespace App\Application\Proposals\DTOs;

use App\Domain\Proposals\Enums\ProposalType;

/**
 * Settings of the proposals from the web (core#570). Proposals are off for both types until the
 * administration turns them on.
 */
final readonly class ProposalSettingsDTO
{
    public const int DEFAULT_MAX_PENDING = 3;

    public const int DEFAULT_MAX_OPEN_DRAFTS = 5;

    public const int DEFAULT_REJECTED_RETENTION_DAYS = 90;

    public const int DEFAULT_SUBMISSIONS_PER_HOUR = 5;

    /** Bounds of every numeric setting, inclusive */
    public const int MIN_LIMIT = 1;

    public const int MAX_LIMIT = 100;

    public const int MAX_RETENTION_DAYS = 3650;

    /**
     * @param  int  $maxPending  proposals awaiting review at once, per person (both types)
     * @param  int  $maxOpenDrafts  open drafts at once, per person (both types)
     * @param  int  $rejectedRetentionDays  days after which rejected proposals are deleted
     * @param  int  $submissionsPerHour  submissions per person and hour (both types)
     */
    public function __construct(
        public ProposalTypeSettingsDTO $events = new ProposalTypeSettingsDTO,
        public ProposalTypeSettingsDTO $articles = new ProposalTypeSettingsDTO,
        public int $maxPending = self::DEFAULT_MAX_PENDING,
        public int $maxOpenDrafts = self::DEFAULT_MAX_OPEN_DRAFTS,
        public int $rejectedRetentionDays = self::DEFAULT_REJECTED_RETENTION_DAYS,
        public int $submissionsPerHour = self::DEFAULT_SUBMISSIONS_PER_HOUR,
    ) {}

    public function forType(ProposalType $type): ProposalTypeSettingsDTO
    {
        return match ($type) {
            ProposalType::Event => $this->events,
            ProposalType::Article => $this->articles,
        };
    }
}
