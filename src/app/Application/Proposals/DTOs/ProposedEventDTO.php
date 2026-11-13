<?php

declare(strict_types=1);

namespace App\Application\Proposals\DTOs;

use DateTimeImmutable;

/**
 * The event of a proposal as the author wrote it (core#612), for the early warnings of a module
 * request. The event may not be published (or, in the panel, may have changed), so a module
 * must not look it up by id: it reads dates and place from here. The dates are instants.
 */
final readonly class ProposedEventDTO
{
    public function __construct(
        public DateTimeImmutable $startDate,
        public DateTimeImmutable $endDate,
        public ?string $location = null,
    ) {}
}
