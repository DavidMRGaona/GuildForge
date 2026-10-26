<?php

declare(strict_types=1);

namespace App\Infrastructure\Authorization\Services;

use App\Application\Authorization\Services\AssociationMembershipServiceInterface;
use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Authorization\Services\BaseRoleRule;
use App\Application\Authorization\Services\RoleAuditLoggerInterface;
use App\Domain\Authorization\Exceptions\RoleHolderLimitReachedException;
use App\Domain\Events\AssociationRoleLost;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class AuthorizationService implements AuthorizationServiceInterface
{
    private const int CACHE_TTL = 3600; // 1 hour

    /**
     * Random token that is part of every cached lookup key: replacing it invalidates all of
     * them at once, and a lookup computed from the old data lands under a token nobody reads
     * again. A missing or malformed token is replaced by a new one, never read as an old one.
     * It is read once per request or job (AuthorizationCacheVersion), not once per lookup.
     */
    private const string CACHE_VERSION_KEY = 'authorization_cache_version';

    /**
     * Each user's own token, followed by the user id: also part of that user's lookup keys,
     * with the same rules, so a change to one user's roles replaces only that user's token.
     */
    private const string USER_CACHE_VERSION_KEY_PREFIX = 'authorization_cache_version_user_';

    private const string CACHE_VERSION_PATTERN = '/^[0-9a-f]{32}$/';

    public function __construct(
        private BaseRoleRule $baseRole = new BaseRoleRule,
        private RoleHolderLimitGuard $holderLimit = new RoleHolderLimitGuard,
        private ?RoleAuditLoggerInterface $audit = null,
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

        return array_any($permissionKeys, fn ($key) => in_array($key, $permissions, true));
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

        return array_all($permissionKeys, fn ($key) => in_array($key, $permissions, true));
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

        return array_any($roleNames, fn ($name) => in_array($name, $roles, true));
    }

    /**
     * The permissions of the user's active roles only (see activeRoles()).
     *
     * @return array<string>
     */
    public function getPermissions(object $user): array
    {
        return $this->cachedLookup('permissions', $user, static function () use ($user): array {
            /** @var UserModel $user */
            $activeIds = self::activeRoles($user)->pluck('id')->all();

            if ($activeIds === []) {
                return [];
            }

            return RoleModel::query()
                ->whereIn('id', $activeIds)
                ->with('permissions')
                ->get()
                ->flatMap(fn (RoleModel $role) => $role->permissions->pluck('key'))
                ->unique()
                ->values()
                ->toArray();
        });
    }

    /**
     * The user's active roles only (see activeRoles()): the frontend gets this list too, so
     * an inactive role must not open anything there. Screens that need every role the user
     * holds read the relationship or RoleRepositoryInterface::getRoleNamesForUser().
     *
     * @return array<string>
     */
    public function getRoles(object $user): array
    {
        return $this->cachedLookup('roles', $user, static function () use ($user): array {
            /** @var UserModel $user */
            return self::activeRoles($user)->pluck('name')->all();
        });
    }

    /**
     * @throws RoleHolderLimitReachedException when the role is full and the user does not hold it yet
     */
    public function assignRole(object $user, string $roleName): void
    {
        /** @var UserModel $user */
        DB::transaction(function () use ($user, $roleName): void {
            $role = RoleModel::where('name', $roleName)->firstOrFail();

            // Already held: no new holder, nothing to check, nothing to log
            if (! $user->roles()->whereKey($role->id)->exists()) {
                $this->holderLimit->assertCanHold($user, $role);
                $user->roles()->attach($role->id);
                $this->audit()->roleAssigned($user, $role->name);
            }

            $this->invalidateCacheFor($user);
        });
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
            DB::transaction(function () use ($user, $role): void {
                // Logged only when the user held it; the entry and the change stand or fall together
                $detached = false;
                if ($user->roles()->detach($role->id) > 0) {
                    $this->audit()->roleRemoved($user, $role->name);
                    $detached = true;
                }

                $this->invalidateCacheFor($user);

                // After the cache invalidation, so its listeners never read stale roles
                if ($detached) {
                    $this->announceAssociationRoleLost($user, [$role->name]);
                }
            });
        }
    }

    /**
     * Checks the holder limit of every role the user does not hold yet before writing: when
     * one is full, nothing changes.
     *
     * @param  array<string>  $roleNames
     *
     * @throws RoleHolderLimitReachedException
     */
    public function syncRoles(object $user, array $roleNames): void
    {
        /** @var UserModel $user */
        DB::transaction(function () use ($user, $roleNames): void {
            // Read from the database, not the cache: a user who has the base role of every account keeps it
            $currentIds = $user->roles()->pluck('roles.id')->all();
            $hasBaseRole = $user->roles()->where('roles.name', BaseRoleRule::NAME)->exists();

            if ($hasBaseRole && ! in_array(BaseRoleRule::NAME, $roleNames, true)) {
                $roleNames[] = BaseRoleRule::NAME;
            }

            $roles = RoleModel::whereIn('name', $roleNames)->get();

            foreach ($roles as $role) {
                if (! in_array($role->id, $currentIds, true)) {
                    $this->holderLimit->assertCanHold($user, $role);
                }
            }

            $namesById = RoleModel::query()->whereIn('id', $currentIds)->pluck('name', 'id')->all()
                + $roles->pluck('name', 'id')->all();
            $changes = $user->roles()->sync($roles->pluck('id')->all());

            // Only the real differences: a role kept as it was leaves no entry
            foreach ($changes['detached'] as $roleId) {
                $this->audit()->roleRemoved($user, (string) $namesById[$roleId]);
            }

            foreach ($changes['attached'] as $roleId) {
                $this->audit()->roleAssigned($user, (string) $namesById[$roleId]);
            }

            $this->invalidateCacheFor($user);

            // After the cache invalidation, so its listeners never read stale roles
            $this->announceAssociationRoleLost(
                $user,
                array_values(array_map(static fn (int|string $roleId): string => (string) $namesById[$roleId], $changes['detached'])),
            );
        });
    }

    /**
     * After the commit, tells listeners that the account lost the association role while it
     * still holds roles that require it (read raw, since those are inactive now). Only the
     * detaches done here count: anonymizing and setting changes never come through.
     *
     * @param  list<string>  $detachedRoleNames
     */
    private function announceAssociationRoleLost(UserModel $user, array $detachedRoleNames): void
    {
        $associationRole = self::associationMemberRoleName();
        if ($associationRole === null || ! in_array($associationRole, $detachedRoleNames, true)) {
            return;
        }

        /** @var list<string> $inactive */
        $inactive = $user->roles()->where('roles.requires_association_member', true)->pluck('roles.name')->values()->all();
        if ($inactive === []) {
            return;
        }

        $userId = (string) $user->getKey();
        DB::afterCommit(static function () use ($userId, $inactive): void {
            Event::dispatch(new AssociationRoleLost($userId, $inactive));
        });
    }

    public function invalidateCache(): void
    {
        // After the commit: invalidating earlier lets a request in between cache the old
        // data again under the new version. Runs at once outside a transaction.
        DB::afterCommit(static function (): void {
            $current = app(AuthorizationCacheVersion::class);
            // Until the new token is stored: if storing fails, this request reads the store again
            $current->token = null;
            $current->token = self::replaceToken(self::CACHE_VERSION_KEY);
        });
    }

    public function invalidateCacheFor(object $user): void
    {
        $userId = $this->getUserId($user);

        DB::afterCommit(static function () use ($userId): void {
            $current = app(AuthorizationCacheVersion::class);
            unset($current->userTokens[$userId]);
            $current->userTokens[$userId] = self::replaceToken(self::USER_CACHE_VERSION_KEY_PREFIX.$userId);
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
            // Fixed prefix, server-side version tokens and the user's primary key: nothing user supplied
            $userId = $this->getUserId($user);
            $current = app(AuthorizationCacheVersion::class);
            $version = $current->token ??= $this->readToken(self::CACHE_VERSION_KEY);
            $userVersion = $current->userTokens[$userId] ??= $this->readToken(self::USER_CACHE_VERSION_KEY_PREFIX.$userId);
            $key = "user_{$kind}_{$version}_{$userVersion}_{$userId}";
            $cached = Cache::get($key);

            if ($this->isListOfNames($cached)) {
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

    /**
     * The token stored under $key. Callers keep it in AuthorizationCacheVersion, so it is
     * read once per request or job.
     */
    private function readToken(string $key): string
    {
        $version = Cache::get($key);

        if (! is_string($version) || preg_match(self::CACHE_VERSION_PATTERN, $version) !== 1) {
            // Missing (evicted, flushed) or malformed: start a version no cached lookup can match
            $version = self::newCacheVersion();
            Cache::forever($key, $version);
        }

        return $version;
    }

    /**
     * Writes a new token under $key, so every lookup cached under the old one is left behind.
     */
    private static function replaceToken(string $key): string
    {
        $version = self::newCacheVersion();

        try {
            Cache::forever($key, $version);

            return $version;
        } catch (Throwable $e) {
            Log::error("Authorization cache could not be invalidated ({$key}): {$e->getMessage()}");

            // Without a token readers start a new one, so the old entries are never served
            try {
                Cache::forget($key);
            } catch (Throwable) {
                // Store unreachable: readers fall back to the database meanwhile
            }

            // The change is committed: the admin must still see that access may lag
            throw $e;
        }
    }

    private static function newCacheVersion(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * @phpstan-assert-if-true array<int, string> $value
     */
    private function isListOfNames(mixed $value): bool
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return false;
        }

        return array_all($value, fn ($name) => is_string($name));
    }

    /**
     * The roles that count for the user. A role that requires association membership is
     * active only while the user's own roles (unfiltered) include the association member
     * role; when no association member role is usable (roleName() is null) none of them is.
     * Every other role is always active.
     *
     * The setting is read only when one of the roles carries the requirement. If it cannot
     * be read, those roles stay inactive: a failure never grants access.
     *
     * @return Collection<int, RoleModel>
     */
    private static function activeRoles(UserModel $user): Collection
    {
        $roles = $user->roles()->get(['roles.id', 'roles.name', 'roles.requires_association_member']);
        $requiresMembership = static fn (RoleModel $role): bool => $role->requires_association_member === true;

        if (! $roles->contains($requiresMembership)) {
            return $roles;
        }

        $associationRole = self::associationMemberRoleName();
        $isAssociationMember = $associationRole !== null
            && $roles->contains(fn (RoleModel $role): bool => $role->name === $associationRole);

        return $isAssociationMember ? $roles : $roles->reject($requiresMembership)->values();
    }

    /**
     * The membership service is resolved here, not injected: it depends on this service.
     * roleName() reads only the setting, never this service.
     */
    private static function associationMemberRoleName(): ?string
    {
        try {
            return app(AssociationMembershipServiceInterface::class)->roleName();
        } catch (Throwable $e) {
            Log::warning("Association member role setting unreadable, roles that require it stay inactive: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Resolved when first needed unless injected: the logger never depends on this service.
     */
    private function audit(): RoleAuditLoggerInterface
    {
        return $this->audit ?? app(RoleAuditLoggerInterface::class);
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
