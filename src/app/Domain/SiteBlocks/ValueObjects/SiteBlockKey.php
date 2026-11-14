<?php

declare(strict_types=1);

namespace App\Domain\SiteBlocks\ValueObjects;

/**
 * The key of a site block: its owner (`core` or the module name) and the block name, joined by
 * a dot, e.g. `core.upcoming_events` or `newsletter.subscribe`. It is what the footer and the
 * popups store, so it never changes once a block is offered.
 */
final readonly class SiteBlockKey
{
    public const int MAX_LENGTH = 100;

    public const string CORE_OWNER = 'core';

    /** The owner is a module name (kebab-case); the name is snake_case or kebab-case. */
    private const string PATTERN = '/^(?<owner>[a-z0-9]+(?:-[a-z0-9]+)*)\.(?<name>[a-z0-9]+(?:[_-][a-z0-9]+)*)$/';

    private function __construct(
        public string $value,
        private string $owner,
        private string $name,
    ) {}

    public static function tryFrom(string $key): ?self
    {
        if (strlen($key) > self::MAX_LENGTH || preg_match(self::PATTERN, $key, $match) !== 1) {
            return null;
        }

        return new self($key, $match['owner'], $match['name']);
    }

    /** `core` or the name of the module that owns the block */
    public function owner(): string
    {
        return $this->owner;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function isCore(): bool
    {
        return $this->owner === self::CORE_OWNER;
    }
}
