<?php

declare(strict_types=1);

namespace App\Infrastructure\Authorization\Services;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Authorization\Services\BaseRoleRule;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class AuthorizationService implements AuthorizationServiceInterface
{
    private const int CACHE_TTL = 3600; // 1 hour

    /**
     * Random token that is part of every cached lookup key: replacing it invalidates all of
     * them at once, and a lookup computed from the old data lands under a token nobody reads
     * again. A missing or malformed token is replaced by a new one, never read as an old one.
     * It is read once per request or job (AuthorizationCacheVersion), not once per lookup.
     */
    private const string CACHE_VERSION_KEY = 'authorization_cache_version';

    private const string CACHE_VERSION_PATTERN = '/^[0-9a-f]{32}$/';

    public function __construct(
        private readonly BaseRoleRule $baseRole = new BaseRoleRule,
    ) {}

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
        return $this->cachedLookup('permissions', $user, static function () use ($user): array {
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
        return $this->cachedLookup('roles', $user, static function () use ($user): array {
            /** @var UserModel $user */
            return $user->roles()->pluck('name')->toArray();
        });
    }

    public function assignRole(object $user, string $roleName): void
    {
        $role = RoleModel::where('name', $roleName)->firstOrFail();
        /** @var UserModel $user */
        $user->roles()->syncWithoutDetaching([$role->id]);
        $this->invalidateCache();
    }

    public function removeRole(object $user, string $roleName): void
    {
        // The base role of every account is never taken away: nothing changes, nothing to invalidate
        if ($this->baseRole->isBaseRoleName($roleName)) {
            return;
        }

        $role = RoleModel::where('name', $roleName)->first();
        if ($role !== null) {
            /** @var UserModel $user */
            $user->roles()->detach($role->id);
            $this->invalidateCache();
        }
    }

    /**
     * @param  array<string>  $roleNames
     */
    public function syncRoles(object $user, array $roleNames): void
    {
        /** @var UserModel $user */
        // Read from the database, not the cache: a user who has the base role of every account keeps it
        $hasBaseRole = $user->roles()->where('roles.name', BaseRoleRule::NAME)->exists();

        if ($hasBaseRole && ! in_array(BaseRoleRule::NAME, $roleNames, true)) {
            $roleNames[] = BaseRoleRule::NAME;
        }

        $roleIds = RoleModel::whereIn('name', $roleNames)->pluck('id')->toArray();
        $user->roles()->sync($roleIds);
        $this->invalidateCache();
    }

    public function invalidateCache(): void
    {
        // After the commit: invalidating earlier lets a request in between cache the old
        // data again under the new version. Runs at once outside a transaction.
        DB::afterCommit(static function (): void {
            $current = app(AuthorizationCacheVersion::class);
            $version = self::newCacheVersion();

            try {
                Cache::forever(self::CACHE_VERSION_KEY, $version);
                $current->token = $version;
            } catch (Throwable $e) {
                $current->token = null;
                Log::error("Authorization cache could not be invalidated: {$e->getMessage()}");

                // Without a token readers start a new one, so the old entries are never served
                try {
                    Cache::forget(self::CACHE_VERSION_KEY);
                } catch (Throwable) {
                    // Store unreachable: readers fall back to the database meanwhile
                }

                // The change is committed: the admin must still see that access may lag
                throw $e;
            }
        });
    }

    /**
     * The cache only saves queries: when it is unreachable or holds anything but a list of
     * names, the answer comes from the database, so a cache problem can never grant access.
     *
     * @param  callable(): array<string>  $query
     * @return array<string>
     */
    private function cachedLookup(string $kind, object $user, callable $query): array
    {
        $key = null;

        try {
            // Fixed prefix, server-side version token and the user's primary key: nothing user supplied
            $key = "user_{$kind}_{$this->cacheVersion()}_{$this->getUserId($user)}";
            $cached = Cache::get($key);

            if (self::isListOfNames($cached)) {
                return $cached;
            }
        } catch (Throwable $e) {
            Log::warning("Authorization cache unavailable, reading {$kind} from the database: {$e->getMessage()}");
            $key = null;
        }

        $fresh = array_values(array_map(strval(...), $query()));

        if ($key !== null) {
            try {
                Cache::put($key, $fresh, self::CACHE_TTL);
            } catch (Throwable) {
                // Answered from the database; the next lookup tries the cache again
            }
        }

        return $fresh;
    }

    private function cacheVersion(): string
    {
        $current = app(AuthorizationCacheVersion::class);

        if ($current->token !== null) {
            return $current->token;
        }

        $version = Cache::get(self::CACHE_VERSION_KEY);

        if (! is_string($version) || preg_match(self::CACHE_VERSION_PATTERN, $version) !== 1) {
            // Missing (evicted, flushed) or malformed: start a version no cached lookup can match
            $version = self::newCacheVersion();
            Cache::forever(self::CACHE_VERSION_KEY, $version);
        }

        return $current->token = $version;
    }

    private static function newCacheVersion(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * @phpstan-assert-if-true array<int, string> $value
     */
    private static function isListOfNames(mixed $value): bool
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return false;
        }

        foreach ($value as $name) {
            if (! is_string($name)) {
                return false;
            }
        }

        return true;
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
}
