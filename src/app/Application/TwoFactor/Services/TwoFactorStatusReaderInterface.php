<?php

declare(strict_types=1);

namespace App\Application\TwoFactor\Services;

/**
 * Whether accounts have two-factor authentication on, for code that only needs to ask (the
 * sign-in challenge, roles that require it). Kept apart from TwoFactorServiceInterface so a
 * reader can be injected, or replaced in tests, without the whole service.
 */
interface TwoFactorStatusReaderInterface
{
    /**
     * True only for a confirmed setup; false for an unknown account, no setup or one still pending.
     */
    public function isEnabled(string $userId): bool;

    /**
     * The ids among those given (compared in lower case, returned as stored) whose two-factor
     * authentication is on, in one query.
     *
     * @param  list<string>  $userIds
     * @return list<string>
     */
    public function enabledAmong(array $userIds): array;
}
