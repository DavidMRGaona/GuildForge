<?php

declare(strict_types=1);

namespace App\Application\Search\DTOs;

use DateTimeImmutable;

/**
 * One row of the search results, the same shape for every type.
 * Instants are in the storage zone; whoever shows them converts them to the display zone.
 */
final readonly class SiteSearchHitDTO
{
    /**
     * @param  list<array{text: string, match: bool}>  $snippet  plain text segments, never HTML
     */
    public function __construct(
        public string $type,
        public string $title,
        public string $url,
        public ?string $meta,
        public ?string $imagePublicId,
        public ?DateTimeImmutable $startsAt,
        public ?DateTimeImmutable $endsAt,
        public bool $past,
        public array $snippet,
        public ?DateTimeImmutable $publishedAt = null,
    ) {}
}
