<?php

declare(strict_types=1);

namespace App\Infrastructure\Authorization\Services;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Cache;

final class AuthorizationService implements AuthorizationServiceInterface
{
    private const int CACHE_TTL = 3600; // 1 hour

    public function can(object $user, string $permissionKey): bool
    {
        // Fast path: admin has all permissions
        if ($this->isAdmin($user)) {
            return true;
        }

        $permissions = $this->getPermissions($user);

        return in_array($permissionKey, $permissions, true);
    }

    /**
     * @param  array<string>  $permissionKeys
     */
    public function canAny(object $user, array $permissionKeys): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        $permissions = $this->getPermissions($user);
        foreach ($permissionKeys as $key) {
            if (in_array($key, $permissions, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string>  $permissionKeys
     */
    public function canAll(object $user, array $permissionKeys): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        $permissions = $this->getPermissions($user);
        foreach ($permissionKeys as $key) {
            if (! in_array($key, $permissions, true)) {
                return false;
            }
        }

        return true;
    }

    public function hasRole(object $user, string $roleName): bool
    {
        $roles = $this->getRoles($user);

        return in_array($roleName, $roles, true);
    }

    /**
     * @param  array<string>  $roleNames
     */
    public function hasAnyRole(object $user, array $roleNames): bool
    {
        $roles = $this->getRoles($user);
        foreach ($roleNames as $name) {
            if (in_array($name, $roles, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string>
     */
    public function getPermissions(object $user): array
    {
        $userId = $this->getUserId($user);
        $cacheKey = "user_permissions_{$userId}";

        /** @var array<string> */
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user): array {
            /** @var UserModel $user */
            return $user->roles()
                ->with('permissions')
                ->get()
                ->flatMap(fn (RoleModel $role) => $role->permissions->pluck('key'))
                ->unique()
                ->values()
                ->toArray();
        });
    }

    /**
     * @return array<string>
     */
    public function getRoles(object $user): array
    {
        $userId = $this->getUserId($user);
        $cacheKey = "user_roles_{$userId}";

        /** @var array<string> */
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user): array {
            /** @var UserModel $user */
            return $user->roles()->pluck('name')->toArray();
        });
    }

    public function assignRole(object $user, string $roleName): void
    {
        $role = RoleModel::where('name', $roleName)->firstOrFail();
        /** @var UserModel $user */
        $user->roles()->syncWithoutDetaching([$role->id]);
        $this->clearUserCache($user);
    }

    public function removeRole(object $user, string $roleName): void
    {
        $role = RoleModel::where('name', $roleName)->first();
        if ($role !== null) {
            /** @var UserModel $user */
            $user->roles()->detach($role->id);
            $this->clearUserCache($user);
        }
    }

    /**
     * @param  array<string>  $roleNames
     */
    public function syncRoles(object $user, array $roleNames): void
    {
        $roleIds = RoleModel::whereIn('name', $roleNames)->pluck('id')->toArray();
        /** @var UserModel $user */
        $user->roles()->sync($roleIds);
        $this->clearUserCache($user);
    }

    private function isAdmin(object $user): bool
    {
        return $this->hasRole($user, 'admin');
    }

    private function getUserId(object $user): int|string
    {
        /** @var UserModel $user */
        /** @var int|string */
        return $user->id ?? $user->getKey();
    }

    private function clearUserCache(object $user): void
    {
        $userId = $this->getUserId($user);
        Cache::forget("user_permissions_{$userId}");
        Cache::forget("user_roles_{$userId}");
    }
}
