<?php

declare(strict_types=1);

namespace App\Application\Profiles\DTOs;

/**
 * What an address gives one viewer (core#741): the profile, where it moved, or nothing. The last one is
 * the same whether the address is unknown, hidden or forbidden to them.
 */
final readonly class ProfileLookupDTO
{
    private function __construct(
        public ?PublicProfileDTO $profile,
        /** The current address, when the one asked for is old or written differently */
        public ?string $movedTo,
        public bool $isOwner,
        /** The owner shows the membership badge and is an association member */
        public bool $membershipBadge,
    ) {}

    public static function notFound(): self
    {
        return new self(null, null, false, false);
    }

    public static function movedTo(string $handle): self
    {
        return new self(null, $handle, false, false);
    }

    public static function visible(PublicProfileDTO $profile, bool $isOwner, bool $membershipBadge): self
    {
        return new self($profile, null, $isOwner, $membershipBadge);
    }
}
