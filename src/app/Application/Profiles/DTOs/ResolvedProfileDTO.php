<?php

declare(strict_types=1);

namespace App\Application\Profiles\DTOs;

use App\Domain\Profiles\ProfileRegion;

/**
 * A public profile drawn for one person: the header, the stats in the owner's order, and the
 * sections of each region in the owner's order, all of them already filtered to what can be drawn.
 */
final readonly class ResolvedProfileDTO
{
    /** How many stats a summary card shows: the first ones of `stats` */
    public const int CARD_STATS = 4;

    /**
     * @param  list<ResolvedProfileStatDTO>  $stats
     * @param  list<ResolvedProfileSectionDTO>  $main
     * @param  list<ResolvedProfileSectionDTO>  $aside
     */
    public function __construct(
        public ProfileHeaderDTO $header,
        public array $stats,
        public array $main,
        public array $aside,
    ) {}

    /**
     * @return list<ResolvedProfileSectionDTO>
     */
    public function sections(ProfileRegion $region): array
    {
        return match ($region) {
            ProfileRegion::Main => $this->main,
            ProfileRegion::Aside => $this->aside,
        };
    }

    /**
     * Whether nothing but the header is drawn.
     */
    public function isEmpty(): bool
    {
        return $this->stats === [] && $this->main === [] && $this->aside === [];
    }

    /**
     * What `@/types/profileSections` calls `ResolvedProfile`.
     *
     * @return array{header: array<string, mixed>, stats: list<array<string, mixed>>, sections: array{main: list<array<string, mixed>>, aside: list<array<string, mixed>>}}
     */
    public function toArray(): array
    {
        return [
            'header' => $this->header->toArray(),
            'stats' => array_map(static fn (ResolvedProfileStatDTO $stat): array => $stat->toArray(), $this->stats),
            'sections' => [
                'main' => $this->sectionsToArray($this->main),
                'aside' => $this->sectionsToArray($this->aside),
            ],
        ];
    }

    /**
     * @param  list<ResolvedProfileSectionDTO>  $sections
     * @return list<array<string, mixed>>
     */
    private function sectionsToArray(array $sections): array
    {
        return array_map(static fn (ResolvedProfileSectionDTO $section): array => $section->toArray(), $sections);
    }
}
