<?php

declare(strict_types=1);

namespace App\Application\Footer\DTOs;

use App\Application\SiteBlocks\DTOs\ResolvedSiteBlockDTO;

/**
 * The configurable footer for the browser (the shared prop `footer`): its columns of resolved blocks,
 * each with its optional title, and the bottom bar (copyright and the published legal pages).
 */
final readonly class FooterDTO
{
    /**
     * @param  non-empty-list<list<array{title: string|null, block: ResolvedSiteBlockDTO}>>  $columns  only columns with a block left, at least one
     * @param  list<array{label: string, href: string}>  $legalLinks
     */
    public function __construct(
        public array $columns,
        public string $siteName,
        public array $legalLinks,
    ) {}

    /**
     * @return array{columns: list<array{blocks: list<array{title: string|null, block: array<string, mixed>}>}>, siteName: string, legalLinks: list<array{label: string, href: string}>}
     */
    public function toArray(): array
    {
        return [
            'columns' => array_map(
                static fn (array $blocks): array => [
                    'blocks' => array_map(
                        static fn (array $entry): array => ['title' => $entry['title'], 'block' => $entry['block']->toArray()],
                        $blocks,
                    ),
                ],
                $this->columns,
            ),
            'siteName' => $this->siteName,
            'legalLinks' => $this->legalLinks,
        ];
    }
}
