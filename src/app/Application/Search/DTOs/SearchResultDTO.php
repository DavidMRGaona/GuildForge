<?php

declare(strict_types=1);

namespace App\Application\Search\DTOs;

final readonly class SearchResultDTO
{
    /**
     * @param  list<array{text: string, match: bool}>  $snippet  plain text segments, never HTML
     */
    public function __construct(
        public string $title,
        public string $url,
        public ?string $context,
        public array $snippet,
    ) {}

    /**
     * @return array{title: string, url: string, context: ?string, snippet: list<array{text: string, match: bool}>}
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'url' => $this->url,
            'context' => $this->context,
            'snippet' => $this->snippet,
        ];
    }
}
