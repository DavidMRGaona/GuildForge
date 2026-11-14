<?php

declare(strict_types=1);

namespace App\Infrastructure\Impersonation;

use App\Application\Impersonation\DTOs\ViewedRoleDTO;
use App\Application\Impersonation\Services\ImpersonationRolesInterface;
use App\Domain\Enums\UserRole;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use Illuminate\Database\Eloquent\Builder;

final readonly class EloquentImpersonationRoles implements ImpersonationRolesInterface
{
    public function find(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return $this->toDtos($this->ordered()->whereIn('id', array_values(array_unique($ids))));
    }

    public function baseRoleId(): ?string
    {
        $id = RoleModel::query()->where('name', UserRole::Member->value)->value('id');

        return is_string($id) ? $id : null;
    }

    public function offered(): array
    {
        return $this->toDtos($this->ordered()->where('name', '!=', UserRole::Admin->value));
    }

    /**
     * @return Builder<RoleModel>
     */
    private function ordered(): Builder
    {
        return RoleModel::query()->orderBy('display_name')->orderBy('name');
    }

    /**
     * @param  Builder<RoleModel>  $query
     * @return list<ViewedRoleDTO>
     */
    private function toDtos(Builder $query): array
    {
        return array_values($query->get()
            ->map(static fn (RoleModel $role): ViewedRoleDTO => new ViewedRoleDTO($role->id, $role->name, self::displayName($role)))
            ->all());
    }

    /**
     * Its display name, or its name when it has none: the dialog, the throwaway account, the
     * bands and the log all name the role the same way.
     */
    private static function displayName(RoleModel $role): string
    {
        return trim($role->display_name) !== '' ? $role->display_name : $role->name;
    }
}
