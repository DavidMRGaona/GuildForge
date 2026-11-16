<?php

declare(strict_types=1);

namespace App\Application\TwoFactor\Services;

use App\Domain\Authorization\Entities\Role;
use App\Domain\Authorization\Repositories\RoleRepositoryInterface;
use App\Domain\Authorization\ValueObjects\RoleName;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Whether an account may turn two-factor authentication off from «Ajustes de la cuenta»
 * (core#769). Not while it holds a role that requires it (core#772): every role it holds counts,
 * also one paused for another reason (it would pause again the day that reason goes). Both the
 * page (`canDisable`) and the route read this rule, so a hidden button never stands in for it.
 * A failure refuses (fails closed).
 *
 * A concrete class on purpose, so it can grow a dependency without breaking test doubles of an
 * interface.
 */
class TwoFactorDisableRule
{
    public function __construct(
        private readonly ?RoleRepositoryInterface $roles = null,
    ) {}

    public function allowed(string $userId): bool
    {
        try {
            return $this->rolesRequiringIt($userId) === [];
        } catch (Throwable $exception) {
            Log::warning('Could not read the roles of the account, two-factor authentication stays on.', [
                'user_id' => $userId,
                'exception' => $exception::class,
            ]);

            return false;
        }
    }

    /**
     * Display names of the roles the account holds (stored, paused or not) that require two-factor
     * authentication, sorted. Throws when the roles cannot be read.
     *
     * @return list<string>
     */
    public function rolesRequiringIt(string $userId): array
    {
        $repository = $this->roles ?? app(RoleRepositoryInterface::class);
        $names = [];

        foreach ($repository->getRoleNamesForUser($userId) as $roleName) {
            try {
                $role = $repository->findByName(new RoleName($roleName));
            } catch (InvalidArgumentException) {
                // A stored name the domain no longer accepts cannot be told apart: it counts
                $names[] = $roleName;

                continue;
            }

            if (! $role instanceof Role || $role->requiresTwoFactor()) {
                $names[] = $role?->displayName() ?? $roleName;
            }
        }

        sort($names);

        return $names;
    }
}
