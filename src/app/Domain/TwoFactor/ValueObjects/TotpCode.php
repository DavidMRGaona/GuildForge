<?php

declare(strict_types=1);

namespace App\Domain\TwoFactor\ValueObjects;

use App\Domain\TwoFactor\Exceptions\InvalidTwoFactorCodeException;

/**
 * A code from an authenticator app: six ASCII digits. Spaces people type to group it
 * («123 456») are ignored.
 */
final readonly class TotpCode
{
    public const int DIGITS = 6;

    private function __construct(
        public string $value,
    ) {}

    /**
     * @throws InvalidTwoFactorCodeException
     */
    public static function fromString(#[\SensitiveParameter] string $input): self
    {
        return self::tryFrom($input) ?? throw InvalidTwoFactorCodeException::totp();
    }

    public static function tryFrom(#[\SensitiveParameter] string $input): ?self
    {
        $digits = preg_replace('/\s+/u', '', $input);

        if (! is_string($digits) || preg_match('/^[0-9]{'.self::DIGITS.'}\z/', $digits) !== 1) {
            return null;
        }

        return new self($digits);
    }
}
