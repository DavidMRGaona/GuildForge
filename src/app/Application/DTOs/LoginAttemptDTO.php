<?php

declare(strict_types=1);

namespace App\Application\DTOs;

/**
 * The result of AuthServiceInterface::attemptLogin(): the account's id comes only with a right password.
 */
final readonly class LoginAttemptDTO
{
    private function __construct(
        public LoginAttemptOutcome $outcome,
        public ?string $userId,
    ) {}

    public static function failed(): self
    {
        return new self(LoginAttemptOutcome::Failed, null);
    }

    public static function signedIn(string $userId): self
    {
        return new self(LoginAttemptOutcome::SignedIn, $userId);
    }

    public static function twoFactorRequired(string $userId): self
    {
        return new self(LoginAttemptOutcome::TwoFactorRequired, $userId);
    }
}
