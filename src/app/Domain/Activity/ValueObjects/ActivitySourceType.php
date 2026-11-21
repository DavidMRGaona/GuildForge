<?php

declare(strict_types=1);

namespace App\Domain\Activity\ValueObjects;

use App\Domain\SiteBlocks\ValueObjects\SiteBlockKey;

/**
 * The type of an activity source (core#774): its owner (`core` or the module name) and the kind of
 * thing it reports, joined by a dot, e.g. `game-tables.session` or `core.account`. Consumers store
 * it next to every fact, so it never changes once offered. Same rules as site block keys, read
 * whole: nothing, not even a final newline, may follow the name.
 */
final readonly class ActivitySourceType
{
    public const int MAX_LENGTH = SiteBlockKey::MAX_LENGTH;

    public const string CORE_OWNER = SiteBlockKey::CORE_OWNER;

    /** The owner is a module name (kebab-case); the name is snake_case or kebab-case */
    private const string PATTERN = '/^(?<owner>[a-z0-9]+(?:-[a-z0-9]+)*)\.(?<name>[a-z0-9]+(?:[_-][a-z0-9]+)*)$/D';

    private function __construct(
        public string $value,
        private string $owner,
        private string $name,
    ) {}

    public static function tryFrom(string $type): ?self
    {
        if (strlen($type) > self::MAX_LENGTH || preg_match(self::PATTERN, $type, $match) !== 1) {
            return null;
        }

        return new self($type, $match['owner'], $match['name']);
    }

    /** `core` or the name of the module that owns the source */
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
