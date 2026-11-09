<?php

declare(strict_types=1);

namespace App\Domain\Mail\ValueObjects;

use DateTimeImmutable;

/**
 * One notice kept for a person's daily digest instead of being sent (core#333).
 */
final readonly class MailDigestItem
{
    /**
     * @param  string  $line  One line, already written in the language it was kept in.
     * @param  string|null  $subjectType  Kind of the one thing the notice is about, when it said so.
     */
    public function __construct(
        public string $id,
        public string $userId,
        public string $category,
        public string $line,
        public ?string $url,
        public ?string $subjectType,
        public ?string $subjectId,
        public DateTimeImmutable $createdAt,
    ) {}
}
