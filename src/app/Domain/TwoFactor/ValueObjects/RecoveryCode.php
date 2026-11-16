<?php

declare(strict_types=1);

namespace App\Domain\TwoFactor\ValueObjects;

use App\Domain\TwoFactor\Exceptions\InvalidTwoFactorCodeException;

/**
 * A single-use code for when the authenticator app is lost: «xxxxx-xxxxx», lowercase, from an
 * alphabet without characters that look alike (0/o, 1/l/i). Read without caring about case,
 * spaces or the hyphen. About 49 bits of randomness, and only its hash is ever stored.
 */
final readonly class RecoveryCode
{
    public const string ALPHABET = '23456789abcdefghjkmnpqrstuvwxyz';

    public const int GROUP_LENGTH = 5;

    private function __construct(
        public string $value,
    ) {}

    public static function generate(): self
    {
        $last = strlen(self::ALPHABET) - 1;
        $characters = '';

        for ($i = 0; $i < 2 * self::GROUP_LENGTH; $i++) {
            $characters .= self::ALPHABET[random_int(0, $last)];
        }

        return self::fromCharacters($characters);
    }

    /**
     * @throws InvalidTwoFactorCodeException
     */
    public static function fromString(#[\SensitiveParameter] string $input): self
    {
        return self::tryFrom($input) ?? throw InvalidTwoFactorCodeException::recovery();
    }

    public static function tryFrom(#[\SensitiveParameter] string $input): ?self
    {
        $compact = preg_replace('/\s+/u', '', strtolower($input));
        if (! is_string($compact)) {
            return null;
        }

        $group = '['.self::ALPHABET.']{'.self::GROUP_LENGTH.'}';
        if (preg_match('/^('.$group.')-?('.$group.')\z/', $compact, $matches) !== 1) {
            return null;
        }

        return self::fromCharacters($matches[1].$matches[2]);
    }

    /**
     * What is stored: a plain SHA-256 is enough for this much randomness, and the list of hashes
     * is encrypted at rest besides.
     */
    public function hash(): string
    {
        return hash('sha256', $this->value);
    }

    private static function fromCharacters(string $characters): self
    {
        return new self(substr($characters, 0, self::GROUP_LENGTH).'-'.substr($characters, self::GROUP_LENGTH));
    }
}
