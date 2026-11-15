<?php

declare(strict_types=1);

namespace App\Domain\Profiles\ValueObjects;

/**
 * One section or stat in the owner's layout: its key and whether the owner turned it on.
 */
final readonly class ProfileLayoutEntry
{
    public function __construct(
        public string $key,
        public bool $enabled,
    ) {}

    /**
     * @return array{key: string, enabled: bool}
     */
    public function toArray(): array
    {
        return ['key' => $this->key, 'enabled' => $this->enabled];
    }
}
