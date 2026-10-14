<?php

declare(strict_types=1);

namespace App\Application\Authorization\Services;

use App\Domain\Enums\UserRole;

/**
 * The one place that decides whether a role is the base role of every account ("member").
 * Every account gets it (MemberRoleServiceInterface::assignTo()) and modules grant it what any
 * signed-in user needs, so the role panel, the role policy, the role service and the
 * authorization service use this to keep it from being deleted, renamed or taken away.
 *
 * It goes by the role name, not by the "protected" flag: the base role does not carry it.
 * A class of its own rather than a method of a core interface: modules implement those
 * interfaces in their tests, and a new method would break them.
 */
final readonly class BaseRoleRule
{
    public const string NAME = UserRole::Member->value;

    /**
     * Exact, case-sensitive comparison, like role names everywhere else.
     */
    public function isBaseRoleName(string $name): bool
    {
        return $name === self::NAME;
    }
}
