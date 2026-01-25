<?php

declare(strict_types=1);

namespace App\Application\Authorization\DTOs;

final readonly class RoleResponseDTO
{
    /**
     * @param  array<string>  $permissionKeys
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $displayName,
        public ?string $description,
        public bool $isProtected,
        public array $permissionKeys = [],
    ) {
    }

    /**
     * @return array{id: string, name: string, display_name: string, description: string|null, is_protected: bool, permission_keys: array<string>}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'display_name' => $this->displayName,
            'description' => $this->description,
            'is_protected' => $this->isProtected,
            'permission_keys' => $this->permissionKeys,
        ];
    }
}
