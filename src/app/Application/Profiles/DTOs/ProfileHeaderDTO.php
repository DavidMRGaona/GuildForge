<?php

declare(strict_types=1);

namespace App\Application\Profiles\DTOs;

/**
 * The top of a public profile: who it is and since when. The account name and the email never
 * travel here; the membership badge only when the owner chose to show it and they are a socio.
 */
final readonly class ProfileHeaderDTO
{
    /**
     * @param  string|null  $memberSince  when the account was created, ISO 8601 with the display zone's offset
     */
    public function __construct(
        public string $handle,
        public ?string $publicName,
        public ?string $avatarPublicId,
        public ?string $bannerPublicId,
        public ?string $memberSince,
        public bool $showsMembership,
        public bool $isOwner,
    ) {}

    /**
     * @return array{handle: string, publicName: string|null, avatarPublicId: string|null, bannerPublicId: string|null, memberSince: string|null, showsMembership: bool, isOwner: bool}
     */
    public function toArray(): array
    {
        return [
            'handle' => $this->handle,
            'publicName' => $this->publicName,
            'avatarPublicId' => $this->avatarPublicId,
            'bannerPublicId' => $this->bannerPublicId,
            'memberSince' => $this->memberSince,
            'showsMembership' => $this->showsMembership,
            'isOwner' => $this->isOwner,
        ];
    }
}
