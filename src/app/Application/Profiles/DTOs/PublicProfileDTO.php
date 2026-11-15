<?php

declare(strict_types=1);

namespace App\Application\Profiles\DTOs;

use App\Domain\Profiles\ProfileVisibility;

/**
 * What ProfileAccessRule needs to know about the owner of a public profile (core#741). What the page
 * draws comes from ProfileRendererInterface, once the rule let the person see it. Never the account
 * name or email.
 */
final readonly class PublicProfileDTO
{
    public function __construct(
        public string $userId,
        public string $handle,
        public ?string $publicName,
        public ProfileVisibility $visibility,
        public bool $deactivated,
        public bool $anonymized,
    ) {}

    public function hasPublicName(): bool
    {
        return $this->publicName !== null && trim($this->publicName) !== '';
    }

    /**
     * The public name as the page shows it; null when there is none.
     */
    public function shownPublicName(): ?string
    {
        return $this->hasPublicName() ? trim((string) $this->publicName) : null;
    }
}
