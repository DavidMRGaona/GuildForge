<?php

declare(strict_types=1);

namespace App\Domain\Profiles\ValueObjects;

use App\Domain\SiteBlocks\ValueObjects\SiteBlockKey;

/**
 * The key of a profile section or stat: its owner (`core` or the module name) and its name, joined
 * by a dot, e.g. `core.about` or `forum.latest_posts`. It is what the owner's layout stores, so it
 * never changes once offered. The rules are those of site block keys: one rule for both.
 */
final readonly class ProfileKey
{
    public const int MAX_LENGTH = SiteBlockKey::MAX_LENGTH;

    public const string CORE_OWNER = SiteBlockKey::CORE_OWNER;

    private function __construct(
        public string $value,
        private string $owner,
    ) {}

    public static function tryFrom(string $key): ?self
    {
        $parsed = SiteBlockKey::tryFrom($key);

        return $parsed instanceof SiteBlockKey ? new self($parsed->value, $parsed->owner()) : null;
    }

    /** `core` or the name of the module that owns the section or stat */
    public function owner(): string
    {
        return $this->owner;
    }

    public function isCore(): bool
    {
        return $this->owner === self::CORE_OWNER;
    }
}
