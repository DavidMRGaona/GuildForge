<?php

declare(strict_types=1);

namespace App\Application\Authorization\DTOs;

final readonly class UpdateRoleDTO
{
    /**
     * @param  array<string>|null  $permissionKeys  null means don't update permissions
     */
    public function __construct(
        public ?string $displayName = null,
        public ?string $description = null,
        public ?array $permissionKeys = null,
    ) {
    }

    /**
     * @param  array{display_name?: string|null, description?: string|null, permission_keys?: array<string>|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            displayName: $data['display_name'] ?? null,
            description: $data['description'] ?? null,
            permissionKeys: $data['permission_keys'] ?? null,
        );
    }
}
