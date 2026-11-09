<?php

declare(strict_types=1);

namespace App\Domain\Mail\ValueObjects;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * A person's pause of the optional mail. A null end means "until the person resumes".
 */
final readonly class MailPause
{
    public function __construct(
        public ?DateTimeImmutable $until,
    ) {}

    /**
     * Whether the pause still holds mail back at that instant. The end is not included: at the
     * very instant it is reached, the mail goes out again.
     */
    public function isActiveAt(DateTimeInterface $now): bool
    {
        return ! $this->until instanceof DateTimeImmutable || $now < $this->until;
    }
}
