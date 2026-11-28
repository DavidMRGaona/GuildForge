<?php

declare(strict_types=1);

namespace App\Domain\Bots\ValueObjects;

use App\Domain\SiteBlocks\ValueObjects\SiteBlockKey;
use InvalidArgumentException;

/**
 * The key of a bot account: `<module>.<name>`, e.g. `forum.publisher`. It follows the same rules as a
 * site block key (kebab-case module, snake or kebab-case name, at most 100 characters).
 */
final readonly class BotKey
{
    private function __construct(private SiteBlockKey $key) {}

    public static function of(string $key): self
    {
        $parsed = SiteBlockKey::tryFrom($key);

        if (! $parsed instanceof SiteBlockKey) {
            throw new InvalidArgumentException('Invalid bot key.');
        }

        return new self($parsed);
    }

    public function value(): string
    {
        return $this->key->value;
    }

    /** The module that owns the bot */
    public function module(): string
    {
        return $this->key->owner();
    }
}
