<?php

declare(strict_types=1);

namespace App\Application\Impersonation\Services;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * What the impersonation needs to know about accounts, read fresh from the database on
 * every call. Both checks fail closed: an account they cannot read counts as deactivated
 * and anonymized.
 */
interface ImpersonationAccountsInterface
{
    /**
     * The account with that id, deactivated ones included (so the rule can tell why it may
     * not be impersonated); null when there is none.
     */
    public function find(string $id): ?Authenticatable;

    public function isDeactivated(Authenticatable $account): bool;

    public function isAnonymized(Authenticatable $account): bool;
}
