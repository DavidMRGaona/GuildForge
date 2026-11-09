<?php

declare(strict_types=1);

namespace App\Application\Authorization\DTOs;

final readonly class PermissionDefinitionDTO
{
    /**
     * @param  array<string>  $defaultRoles  Roles that should have this permission by default
     * @param  bool  $adminOnly  Only the administrator role may hold it (AdminOnlyPermissionRule)
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $resource,
        public string $action,
        public ?string $module = null,
        public array $defaultRoles = [],
        public ?string $description = null,
        public ?string $group = null,
        public bool $adminOnly = false,
    ) {}

    /**
     * @param  array{key: string, label: string, resource: string, action: string, module?: string|null, default_roles?: array<string>, description?: string|null, group?: string|null, admin_only?: bool}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            key: $data['key'],
            label: $data['label'],
            resource: $data['resource'],
            action: $data['action'],
            module: $data['module'] ?? null,
            defaultRoles: $data['default_roles'] ?? [],
            description: $data['description'] ?? null,
            group: $data['group'] ?? null,
            adminOnly: $data['admin_only'] ?? false,
        );
    }

    /**
     * @return array{key: string, label: string, resource: string, action: string, module: string|null, default_roles: array<string>, description: string|null, group: string|null, admin_only: bool}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'resource' => $this->resource,
            'action' => $this->action,
            'module' => $this->module,
            'default_roles' => $this->defaultRoles,
            'description' => $this->description,
            'group' => $this->group,
            'admin_only' => $this->adminOnly,
        ];
    }

    public function isModulePermission(): bool
    {
        return $this->module !== null;
    }
}
