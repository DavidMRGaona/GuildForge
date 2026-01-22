<?php

declare(strict_types=1);

namespace App\Infrastructure\Modules\Services;

use App\Application\Modules\DTOs\PermissionDTO;
use App\Application\Modules\Services\ModulePermissionRegistryInterface;

final class ModulePermissionRegistry implements ModulePermissionRegistryInterface
{
    /** @var array<string, PermissionDTO> */
    private array $permissions = [];

    public function register(PermissionDTO $permission): void
    {
        $key = $permission->fullName();
        $this->permissions[$key] = $permission;
    }

    public function registerMany(array $permissions): void
    {
        foreach ($permissions as $permission) {
            $this->register($permission);
        }
    }

    public function all(): array
    {
        return array_values($this->permissions);
    }

    public function forModule(string $moduleName): array
    {
        return array_values(
            array_filter(
                $this->permissions,
                fn (PermissionDTO $p): bool => $p->belongsToModule($moduleName)
            )
        );
    }

    public function grouped(): array
    {
        $groups = [];

        foreach ($this->permissions as $permission) {
            $group = $permission->group;
            if (! isset($groups[$group])) {
                $groups[$group] = [];
            }
            $groups[$group][] = $permission;
        }

        ksort($groups);

        return $groups;
    }

    public function find(string $name): ?PermissionDTO
    {
        return $this->permissions[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return isset($this->permissions[$name]);
    }

    public function unregisterModule(string $moduleName): void
    {
        $this->permissions = array_filter(
            $this->permissions,
            fn (PermissionDTO $p): bool => ! $p->belongsToModule($moduleName)
        );
    }

    public function clear(): void
    {
        $this->permissions = [];
    }
}
