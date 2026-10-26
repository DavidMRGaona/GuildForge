<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Repositories;

use App\Domain\Authorization\Entities\Permission;
use App\Domain\Authorization\ValueObjects\PermissionId;
use App\Domain\Authorization\ValueObjects\PermissionKey;

interface PermissionRepositoryInterface
{
    public function findById(PermissionId $id): ?Permission;

    public function findByKey(PermissionKey $key): ?Permission;

    /**
     * @return array<Permission>
     */
    public function findAll(): array;

    /**
     * @return array<Permission>
     */
    public function findByResource(string $resource): array;

    /**
     * @return array<Permission>
     */
    public function findByModule(string $module): array;

    public function save(Permission $permission): void;

    /**
     * @param  array<Permission>  $permissions
     */
    public function saveMany(array $permissions): void;

    // No delete: removing a row takes the permission from every role through the
    // role_permission cascade, with no entry in the role history and no cache invalidation

    /**
     * Find permission IDs by their keys.
     *
     * @param  array<string>  $keys
     * @return array<string> Permission IDs
     */
    public function findIdsByKeys(array $keys): array;
}
