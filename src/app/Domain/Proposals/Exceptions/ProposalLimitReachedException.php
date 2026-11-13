<?php

declare(strict_types=1);

namespace App\Domain\Proposals\Exceptions;

/**
 * A cap of the proposals settings was reached: proposals in review, open drafts or submissions in
 * the last hour.
 */
final class ProposalLimitReachedException extends ProposalException
{
    public const string PENDING = 'pending_limit';

    public const string DRAFTS = 'drafts_limit';

    public const string HOURLY = 'hourly_limit';

    public static function pending(int $limit): self
    {
        return new self(self::PENDING, sprintf('At most %d proposals may await review at once.', $limit));
    }

    public static function drafts(int $limit): self
    {
        return new self(self::DRAFTS, sprintf('At most %d proposal drafts may be open at once.', $limit));
    }

    public static function hourly(int $limit): self
    {
        return new self(self::HOURLY, sprintf('At most %d proposals may be submitted per hour.', $limit));
    }
}
