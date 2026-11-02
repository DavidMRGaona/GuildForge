<?php

declare(strict_types=1);

namespace App\Application\Search\DTOs;

/**
 * Outcome of a sync: documents written and removed, and what went wrong (null when nothing did).
 */
final readonly class SearchSyncResultDTO
{
    public function __construct(
        public int $indexed,
        public int $removed,
        public ?string $error,
    ) {}
}
