<?php

declare(strict_types=1);

namespace App\Infrastructure\Authorization\Repositories;

use App\Domain\Authorization\Entities\Permission;
use App\Domain\Authorization\Repositories\PermissionRepositoryInterface;
use App\Domain\Authorization\ValueObjects\PermissionId;
use App\Domain\Authorization\ValueObjects\PermissionKey;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;

final readonly class EloquentPermissionRepository implements PermissionRepositoryInterface
{
    public function findById(PermissionId $id): ?Permission
    {
        $model = PermissionModel::query()->find($id->value);

        if ($model === null) {
            return null;
        }

        return $this->toDomain($model);
    }

    public function findByKey(PermissionKey $key): ?Permission
    {
        $model = PermissionModel::query()->where('key', $key->value)->first();

        if ($model === null) {
            return null;
        }

        return $this->toDomain($model);
    }

    /**
     * @return array<Permission>
     */
    public function findAll(): array
    {
        /** @var array<Permission> */
        return PermissionModel::query()
            ->orderBy('resource')
            ->orderBy('action')
            ->get()
            ->map(fn (PermissionModel $model): Permission => $this->toDomain($model))
            ->all();
    }

    /**
     * @return array<Permission>
     */
    public function findByResource(string $resource): array
    {
        /** @var array<Permission> */
        return PermissionModel::query()
            ->where('resource', $resource)
            ->orderBy('action')
            ->get()
            ->map(fn (PermissionModel $model): Permission => $this->toDomain($model))
            ->all();
    }

    /**
     * @return array<Permission>
     */
    public function findByModule(string $module): array
    {
        /** @var array<Permission> */
        return PermissionModel::query()
            ->where('module', $module)
            ->orderBy('resource')
            ->orderBy('action')
            ->get()
            ->map(fn (PermissionModel $model): Permission => $this->toDomain($model))
            ->all();
    }

    public function save(Permission $permission): void
    {
        $existing = PermissionModel::query()->where('key', $permission->key()->value)->first();

        if ($existing !== null) {
            // Update existing permission (don't change the ID)
            $existing->update([
                'label' => $permission->label(),
                'resource' => $permission->resource(),
                'action' => $permission->action(),
                'module' => $permission->module(),
            ]);
        } else {
            // Create new permission with the provided ID
            PermissionModel::query()->create($this->toArray($permission));
        }
    }

    /**
     * @param  array<Permission>  $permissions
     */
    public function saveMany(array $permissions): void
    {
        foreach ($permissions as $permission) {
            $this->save($permission);
        }
    }

    public function delete(PermissionId $id): void
    {
        PermissionModel::query()->where('id', $id->value)->delete();
    }

    public function deleteByModule(string $module): void
    {
        PermissionModel::query()->where('module', $module)->delete();
    }

    /**
     * @param  array<string>  $keys
     * @return array<string>
     */
    public function findIdsByKeys(array $keys): array
    {
        return PermissionModel::whereIn('key', $keys)
            ->pluck('id')
            ->toArray();
    }

    private function toDomain(PermissionModel $model): Permission
    {
        return new Permission(
            id: new PermissionId($model->id),
            key: new PermissionKey($model->key),
            label: $model->label,
            resource: $model->resource,
            action: $model->action,
            module: $model->module,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(Permission $permission): array
    {
        return [
            'id' => $permission->id()->value,
            'key' => $permission->key()->value,
            'label' => $permission->label(),
            'resource' => $permission->resource(),
            'action' => $permission->action(),
            'module' => $permission->module(),
        ];
    }
}
