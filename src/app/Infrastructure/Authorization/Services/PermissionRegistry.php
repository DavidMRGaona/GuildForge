<?php

declare(strict_types=1);

namespace App\Infrastructure\Authorization\Services;

use App\Application\Authorization\DTOs\PermissionDefinitionDTO;
use App\Application\Authorization\Services\PermissionRegistryInterface;
use App\Domain\Authorization\Entities\Permission;
use App\Domain\Authorization\Repositories\PermissionRepositoryInterface;
use App\Domain\Authorization\ValueObjects\PermissionId;
use App\Domain\Authorization\ValueObjects\PermissionKey;

final class PermissionRegistry implements PermissionRegistryInterface
{
    /**
     * @var array<string, PermissionDefinitionDTO>
     */
    private array $permissions = [];

    public function __construct(
        private readonly ?PermissionRepositoryInterface $permissionRepository = null,
    ) {
    }

    public function register(PermissionDefinitionDTO $permission): void
    {
        $this->permissions[$permission->key] = $permission;
    }

    /**
     * @param  array<PermissionDefinitionDTO>  $permissions
     */
    public function registerMany(array $permissions): void
    {
        foreach ($permissions as $permission) {
            $this->register($permission);
        }
    }

    /**
     * @return array<PermissionDefinitionDTO>
     */
    public function all(): array
    {
        return array_values($this->permissions);
    }

    public function find(string $key): ?PermissionDefinitionDTO
    {
        return $this->permissions[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return isset($this->permissions[$key]);
    }

    /**
     * @return array<PermissionDefinitionDTO>
     */
    public function forResource(string $resource): array
    {
        return array_values(
            array_filter(
                $this->permissions,
                fn (PermissionDefinitionDTO $permission): bool => $permission->resource === $resource
            )
        );
    }

    /**
     * @return array<PermissionDefinitionDTO>
     */
    public function forModule(string $module): array
    {
        return array_values(
            array_filter(
                $this->permissions,
                fn (PermissionDefinitionDTO $permission): bool => $permission->module === $module
            )
        );
    }

    /**
     * @return array<string, array<PermissionDefinitionDTO>>
     */
    public function grouped(): array
    {
        $grouped = [];

        foreach ($this->permissions as $permission) {
            $grouped[$permission->resource][] = $permission;
        }

        ksort($grouped);

        return $grouped;
    }

    public function unregisterModule(string $module): void
    {
        $this->permissions = array_filter(
            $this->permissions,
            fn (PermissionDefinitionDTO $permission): bool => $permission->module !== $module
        );
    }

    public function syncToDatabase(): void
    {
        if ($this->permissionRepository === null) {
            return;
        }

        $permissions = [];

        foreach ($this->permissions as $dto) {
            $permissions[] = new Permission(
                id: PermissionId::generate(),
                key: new PermissionKey($dto->key),
                label: $dto->label,
                resource: $dto->resource,
                action: $dto->action,
                module: $dto->module,
            );
        }

        $this->permissionRepository->saveMany($permissions);
    }

    public function clear(): void
    {
        $this->permissions = [];
    }
}
