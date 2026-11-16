<?php

declare(strict_types=1);

namespace App\Domain\TwoFactor\Events;

/**
 * The administration took away the account's two-factor authentication (core#771), from the panel or
 * the console. Dispatched after the commit; it mails the owner.
 */
final readonly class TwoFactorReset
{
    /**
     * @param  string|null  $actorId  who did it from the panel; null from the console
     */
    public function __construct(
        public string $userId,
        public ?string $actorId = null,
    ) {}
}
