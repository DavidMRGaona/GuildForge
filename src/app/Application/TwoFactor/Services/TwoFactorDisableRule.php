<?php

declare(strict_types=1);

namespace App\Application\TwoFactor\Services;

/**
 * Whether an account may turn two-factor authentication off from «Ajustes de la cuenta»
 * (core#769). Always, for now: roles that require it (core#772) will refuse here, and both the
 * page (`canDisable`) and the route read this rule, so a hidden button never stands in for it.
 *
 * A concrete class on purpose, so it can grow a dependency without breaking test doubles of an
 * interface.
 */
class TwoFactorDisableRule
{
    public function allowed(string $userId): bool
    {
        return true;
    }
}
