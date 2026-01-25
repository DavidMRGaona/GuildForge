<?php

declare(strict_types=1);

namespace App\Infrastructure\Authorization\Repositories;

use App\Domain\Authorization\Entities\Role;
use App\Domain\Authorization\Repositories\RoleRepositoryInterface;
use App\Domain\Authorization\ValueObjects\RoleId;
use App\Domain\Authorization\ValueObjects\RoleName;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;

final readonly class EloquentRoleRepository implements RoleRepositoryInterface
{
    public function findById(RoleId $id): ?Role
    {
        $model = RoleModel::query()->find($id->value);

        if ($model === null) {
            return null;
        }

        return $this->toDomain($model);
    }

    public function findByName(RoleName $name): ?Role
    {
        $model = RoleModel::query()->where('name', $name->value)->first();

        if ($model === null) {
            return null;
        }

        return $this->toDomain($model);
    }

    /**
     * @return array<Role>
     */
    public function findAll(): array
    {
        /** @var array<Role> */
        return RoleModel::query()
            ->orderBy('name')
            ->get()
            ->map(fn (RoleModel $model): Role => $this->toDomain($model))
            ->all();
    }

    public function save(Role $role): void
    {
        RoleModel::query()->updateOrCreate(
            ['id' => $role->id()->value],
            $this->toArray($role),
        );
    }

    public function delete(RoleId $id): void
    {
        RoleModel::query()->where('id', $id->value)->delete();
    }

    public function syncPermissions(RoleId $roleId, array $permissionIds): void
    {
        $roleModel = RoleModel::find($roleId->value);
        if ($roleModel !== null) {
            $roleModel->permissions()->sync($permissionIds);
        }
    }

    public function attachPermissions(RoleId $roleId, array $permissionIds): void
    {
        $roleModel = RoleModel::find($roleId->value);
        if ($roleModel !== null) {
            $roleModel->permissions()->syncWithoutDetaching($permissionIds);
        }
    }

    /**
     * @return array<string>
     */
    public function getPermissionKeys(RoleId $roleId): array
    {
        $roleModel = RoleModel::find($roleId->value);
        if ($roleModel === null) {
            return [];
        }

        return $roleModel->permissions()->pluck('key')->toArray();
    }

    private function toDomain(RoleModel $model): Role
    {
        return new Role(
            id: new RoleId($model->id),
            name: new RoleName($model->name),
            displayName: $model->display_name,
            description: $model->description,
            isProtected: $model->is_protected,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(Role $role): array
    {
        return [
            'id' => $role->id()->value,
            'name' => $role->name()->value,
            'display_name' => $role->displayName(),
            'description' => $role->description(),
            'is_protected' => $role->isProtected(),
        ];
    }
}
