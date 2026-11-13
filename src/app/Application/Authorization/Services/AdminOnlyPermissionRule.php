<?php

declare(strict_types=1);

namespace App\Application\Authorization\Services;

use App\Domain\Enums\UserRole;

/**
 * The one place that decides which permissions only the administrator role may hold: those
 * that run code on the server or change what it runs (installing modules, applying updates),
 * and impersonating another account.
 * With them kept out of every other role, creating roles can be handed to people who are not
 * administrators: under "only grant what you hold" they can never build another administrator.
 *
 * The core keys are fixed here, so they stay reserved even when the registry does not hold
 * their definitions (fails closed); a module marks its own with `adminOnly: true`.
 *
 * A class of its own rather than a method of a core interface: modules implement those
 * interfaces in their tests, and a new method would break them.
 */
final readonly class AdminOnlyPermissionRule
{
    /** @var list<string> */
    public const array CORE_KEYS = ['modules.manage', 'updates.apply', 'users.impersonate'];

    public function __construct(
        private PermissionRegistryInterface $registry,
    ) {}

    public function isAdminOnly(string $permissionKey): bool
    {
        return in_array($permissionKey, self::CORE_KEYS, true)
            || $this->registry->find($permissionKey)?->adminOnly === true;
    }

    public function mayHold(string $roleName, string $permissionKey): bool
    {
        return $roleName === UserRole::Admin->value || ! $this->isAdminOnly($permissionKey);
    }

    /**
     * The permissions among the given ones that the role may not hold.
     *
     * @param  array<string>  $permissionKeys
     * @return list<string>
     */
    public function notAllowedFor(string $roleName, array $permissionKeys): array
    {
        return array_values(array_filter(
            array_unique($permissionKeys),
            fn (string $key): bool => ! $this->mayHold($roleName, $key),
        ));
    }
}
