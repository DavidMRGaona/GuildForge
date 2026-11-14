<?php

declare(strict_types=1);

namespace App\Domain\Footer\ValueObjects;

/**
 * A column of the configurable footer: its blocks, top to bottom.
 */
final readonly class FooterColumn
{
    /**
     * @param  list<FooterBlock>  $blocks
     */
    public function __construct(
        public array $blocks,
    ) {}

    /**
     * @return array{blocks: list<array{key: string, title: string|null, settings: array<string, mixed>}>}
     */
    public function toArray(): array
    {
        return [
            'blocks' => array_map(static fn (FooterBlock $block): array => $block->toArray(), $this->blocks),
        ];
    }
}
