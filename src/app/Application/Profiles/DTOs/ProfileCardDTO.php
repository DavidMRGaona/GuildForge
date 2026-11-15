<?php

declare(strict_types=1);

namespace App\Application\Profiles\DTOs;

/**
 * The summary card of a public profile for one person (core#744): what `GET /miembros/{handle}/tarjeta`
 * answers and what PublicProfileServiceInterface::card() gives modules. Only what the profile page
 * itself shows that person: never the account id, the account name, the email or the roles.
 */
final readonly class ProfileCardDTO
{
    /** Side of the square avatar, in pixels (twice what a card draws, for dense screens) */
    public const int AVATAR_SIZE = 128;

    /** The banner's box, in pixels: the 3:1 of the page's banner */
    public const int BANNER_WIDTH = 600;

    public const int BANNER_HEIGHT = 200;

    /**
     * @param  string  $url  the profile page, absolute
     * @param  string|null  $publicName  null only on the owner's own card, while they have none
     * @param  string|null  $avatarUrl  https image URL, AVATAR_SIZE square; null without avatar
     * @param  string|null  $bannerUrl  https image URL, BANNER_WIDTH × BANNER_HEIGHT; null without banner
     * @param  string|null  $memberSince  when the account was created, ISO 8601 with the display zone's offset
     * @param  list<ResolvedProfileStatDTO>  $stats  at most ResolvedProfileDTO::CARD_STATS, in the owner's order
     */
    public function __construct(
        public string $handle,
        public ?string $publicName,
        public string $url,
        public ?string $avatarUrl,
        public ?string $bannerUrl,
        public ?string $memberSince,
        public bool $showsMembership,
        public bool $isOwner,
        public array $stats,
    ) {}

    /**
     * The JSON of `GET /miembros/{handle}/tarjeta` (docs/modules/profile-sections.md → Profile card).
     *
     * @return array{handle: string, publicName: string|null, url: string, avatarUrl: string|null, bannerUrl: string|null, memberSince: string|null, showsMembership: bool, isOwner: bool, stats: list<array{key: string, label: string, value: int, url: string|null}>}
     */
    public function toArray(): array
    {
        return [
            'handle' => $this->handle,
            'publicName' => $this->publicName,
            'url' => $this->url,
            'avatarUrl' => $this->avatarUrl,
            'bannerUrl' => $this->bannerUrl,
            'memberSince' => $this->memberSince,
            'showsMembership' => $this->showsMembership,
            'isOwner' => $this->isOwner,
            'stats' => array_map(static fn (ResolvedProfileStatDTO $stat): array => $stat->toArray(), $this->stats),
        ];
    }
}
