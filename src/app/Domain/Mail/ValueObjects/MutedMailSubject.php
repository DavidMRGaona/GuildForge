<?php

declare(strict_types=1);

namespace App\Domain\Mail\ValueObjects;

use DateTimeImmutable;

/**
 * One thing a person muted, with the names it had when they muted it.
 */
final readonly class MutedMailSubject
{
    /**
     * @param  string|null  $typeLabel  Translation key of the kind of thing, as the module gave it.
     */
    public function __construct(
        public string $type,
        public string $id,
        public string $label,
        public ?string $typeLabel,
        public DateTimeImmutable $mutedAt,
    ) {}
}
