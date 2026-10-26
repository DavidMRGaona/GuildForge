<?php

declare(strict_types=1);

namespace App\Infrastructure\Authorization\Services;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Eloquent\Builder;

/**
 * Accounts that hold a role requiring association membership (so the role is inactive for
 * them) without being members: they lack the association member role. Deactivated
 * (soft-deleted) and anonymized accounts are left out.
 */
final readonly class InactiveRoleHolders
{
    /**
     * @param  string|null  $associationRoleName  the association member role; null means nobody is a member
     * @return Builder<UserModel>
     */
    public function query(?string $associationRoleName): Builder
    {
        $query = UserModel::query()
            ->whereNull('anonymized_at')
            ->whereHas('roles', static function (Builder $roles): void {
                $roles->where('requires_association_member', true);
            });

        if ($associationRoleName !== null) {
            $query->whereDoesntHave('roles', static function (Builder $roles) use ($associationRoleName): void {
                $roles->where('name', $associationRoleName);
            });
        }

        return $query;
    }

    public function count(?string $associationRoleName): int
    {
        return $this->query($associationRoleName)->count();
    }
}
