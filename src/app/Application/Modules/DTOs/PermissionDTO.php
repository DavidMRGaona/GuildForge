<?php

declare(strict_types=1);

namespace App\Application\Modules\DTOs;

final readonly class PermissionDTO
{
    /**
     * @param array<string> $roles Roles that have this permission by default
     */
    public function __construct(
        public string $name,
        public string $label,
        public string $group,
        public ?string $description = null,
        public ?string $module = null,
        public array $roles = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? '',
            label: $data['label'] ?? '',
            group: $data['group'] ?? 'default',
            description: $data['description'] ?? null,
            module: $data['module'] ?? null,
            roles: $data['roles'] ?? [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'group' => $this->group,
            'description' => $this->description,
            'module' => $this->module,
            'roles' => $this->roles,
        ];
    }

    /**
     * Get the full permission name with module prefix.
     */
    public function fullName(): string
    {
        if ($this->module !== null) {
            return "{$this->module}.{$this->name}";
        }

        return $this->name;
    }

    /**
     * Check if this permission belongs to a specific module.
     */
    public function belongsToModule(string $moduleName): bool
    {
        return $this->module === $moduleName;
    }
}
