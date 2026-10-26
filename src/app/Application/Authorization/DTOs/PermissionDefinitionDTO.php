<?php

declare(strict_types=1);

namespace App\Application\Authorization\DTOs;

final readonly class PermissionDefinitionDTO
{
    /**
     * @param  array<string>  $defaultRoles  Roles that should have this permission by default
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
    ) {}

    /**
     * @param  array{key: string, label: string, resource: string, action: string, module?: string|null, default_roles?: array<string>, description?: string|null, group?: string|null}  $data
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
        );
    }

    /**
     * @return array{key: string, label: string, resource: string, action: string, module: string|null, default_roles: array<string>, description: string|null, group: string|null}
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
        ];
    }

    public function isModulePermission(): bool
    {
        return $this->module !== null;
    }
}
