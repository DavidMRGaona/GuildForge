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

    public function delete(PermissionId $id): void;

    public function deleteByModule(string $module): void;

    /**
     * Find permission IDs by their keys.
     *
     * @param  array<string>  $keys
     * @return array<string>  Permission IDs
     */
    public function findIdsByKeys(array $keys): array;
}
