<?php

declare(strict_types=1);

namespace App\Application\DTOs\Response;

final readonly class SitemapEntryDTO
{
    public function __construct(
        public string $loc,
        public ?string $lastmod,
        public string $priority,
        public string $changefreq,
    ) {}

    /**
     * @return array{loc: string, lastmod: string|null, priority: string, changefreq: string}
     */
    public function toArray(): array
    {
        return [
            'loc' => $this->loc,
            'lastmod' => $this->lastmod,
            'priority' => $this->priority,
            'changefreq' => $this->changefreq,
        ];
    }
}
