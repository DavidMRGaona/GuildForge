<?php

declare(strict_types=1);

namespace App\Application\Authorization\DTOs;

final readonly class CreateRoleDTO
{
    /**
     * @param  array<string>  $permissionKeys
     */
    public function __construct(
        public string $name,
        public string $displayName,
        public ?string $description = null,
        public bool $isProtected = false,
        public array $permissionKeys = [],
    ) {
    }

    /**
     * @param  array{name: string, display_name: string, description?: string|null, is_protected?: bool, permission_keys?: array<string>}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            displayName: $data['display_name'],
            description: $data['description'] ?? null,
            isProtected: $data['is_protected'] ?? false,
            permissionKeys: $data['permission_keys'] ?? [],
        );
    }
}
