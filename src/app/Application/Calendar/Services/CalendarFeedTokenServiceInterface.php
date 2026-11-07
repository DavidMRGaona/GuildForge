<?php

declare(strict_types=1);

namespace App\Application\Calendar\Services;

use DateTimeImmutable;

/**
 * Secret tokens of the personal calendar subscription feeds, one per account.
 * Only a hash is stored: the token is shown once, when it is issued.
 */
interface CalendarFeedTokenServiceInterface
{
    /**
     * Issue a new token for the account, replacing (and so retiring) any previous one.
     */
    public function issue(string $userId): string;

    /**
     * Remove the account's token, if any.
     */
    public function revoke(string $userId): void;

    /**
     * When the account's current token was issued, or null when it has none.
     */
    public function issuedAt(string $userId): ?DateTimeImmutable;

    /**
     * The account a token belongs to, or null for an unknown or malformed token.
     * It does not check the account itself (deactivated, anonymized...).
     */
    public function userIdFor(string $token): ?string;
}
