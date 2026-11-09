<?php

declare(strict_types=1);

namespace App\Application\Modules\DTOs;

final readonly class PermissionDTO
{
    /**
     * @param  array<string>  $roles  Roles that have this permission by default
     * @param  bool  $adminOnly  Only the administrator role may hold it: for permissions that run
     *                           code on the server or could take the site down
     */
    public function __construct(
        public string $name,
        public string $label,
        public string $group,
        public ?string $description = null,
        public ?string $module = null,
        public array $roles = [],
        public bool $adminOnly = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data
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
            adminOnly: (bool) ($data['admin_only'] ?? false),
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
            'admin_only' => $this->adminOnly,
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
