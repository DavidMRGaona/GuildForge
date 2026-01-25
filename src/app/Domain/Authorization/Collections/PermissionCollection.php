<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Collections;

use App\Domain\Authorization\Entities\Permission;
use App\Domain\Authorization\ValueObjects\PermissionKey;

final class PermissionCollection
{
    /**
     * @var array<string, Permission>
     */
    private array $permissions = [];

    public function __construct(Permission ...$permissions)
    {
        foreach ($permissions as $permission) {
            $this->permissions[(string) $permission->key()] = $permission;
        }
    }

    public function add(Permission $permission): void
    {
        $this->permissions[(string) $permission->key()] = $permission;
    }

    /**
     * @param  array<Permission>  $permissions
     */
    public function addMany(array $permissions): void
    {
        foreach ($permissions as $permission) {
            $this->add($permission);
        }
    }

    /**
     * @return array<Permission>
     */
    public function all(): array
    {
        return array_values($this->permissions);
    }

    public function findByKey(PermissionKey $key): ?Permission
    {
        return $this->permissions[(string) $key] ?? null;
    }

    public function has(PermissionKey $key): bool
    {
        return isset($this->permissions[(string) $key]);
    }

    public function forResource(string $resource): self
    {
        $filtered = array_filter(
            $this->permissions,
            fn (Permission $permission): bool => $permission->resource() === $resource
        );

        return new self(...array_values($filtered));
    }

    public function forModule(string $module): self
    {
        $filtered = array_filter(
            $this->permissions,
            fn (Permission $permission): bool => $permission->module() === $module
        );

        return new self(...array_values($filtered));
    }

    /**
     * @return array<string, array<Permission>>
     */
    public function grouped(): array
    {
        $grouped = [];

        foreach ($this->permissions as $permission) {
            $resource = $permission->resource();
            if (! isset($grouped[$resource])) {
                $grouped[$resource] = [];
            }
            $grouped[$resource][] = $permission;
        }

        return $grouped;
    }

    public function remove(PermissionKey $key): void
    {
        unset($this->permissions[(string) $key]);
    }

    public function count(): int
    {
        return count($this->permissions);
    }

    public function isEmpty(): bool
    {
        return $this->permissions === [];
    }
}
