<?php

declare(strict_types=1);

namespace App\Application\Profiles\DTOs;

use App\Domain\Profiles\ProfileVisibility;
use DateTimeImmutable;

/**
 * What the profile page knows about its owner before deciding who may see it (core#741). Never the
 * account name or email: the page shows the public name only.
 */
final readonly class PublicProfileDTO
{
    public function __construct(
        public string $userId,
        public string $handle,
        public ?string $publicName,
        public ProfileVisibility $visibility,
        public ?string $avatarPublicId,
        public ?string $bannerPublicId,
        /** The owner chose to show the membership badge; whether they are a member is asked apart */
        public bool $showMembership,
        /** When the account was created, in the storage zone */
        public ?DateTimeImmutable $memberSince,
        public bool $deactivated,
        public bool $anonymized,
    ) {}

    public function hasPublicName(): bool
    {
        return $this->publicName !== null && trim($this->publicName) !== '';
    }
}
