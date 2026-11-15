<?php

declare(strict_types=1);

namespace App\Infrastructure\Profiles;

use App\Domain\Profiles\ValueObjects\ProfileHandle;
use Generator;
use Illuminate\Support\Str;

/**
 * The addresses to try, in order, for a public name (core#740): its slug cut to 30 characters, then
 * `-2` to `-20`, then a random suffix. Without a public name (or one with nothing to slug) only random
 * `miembro-xxxxxx` addresses, so the account name never reaches an address.
 */
final class ProfileHandleCandidates
{
    /** Lowercase letters and digits without look-alikes (0/o, 1/l/i) */
    public const string RANDOM_ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

    public const int RANDOM_LENGTH = 6;

    public const string RANDOM_PREFIX = 'miembro';

    /** The slug and its numbered forms: the slug itself is the first */
    private const int NUMBERED_TRIES = 20;

    private const int RANDOM_TRIES = 10;

    /**
     * @return Generator<int, ProfileHandle>
     */
    public static function for(?string $publicName): Generator
    {
        $base = self::baseOf($publicName);

        if ($base === '') {
            yield from self::random(self::RANDOM_PREFIX);

            return;
        }

        if (($handle = ProfileHandle::tryFrom($base)) instanceof ProfileHandle) {
            yield $handle;
        }

        for ($number = 2; $number <= self::NUMBERED_TRIES; $number++) {
            if (($handle = ProfileHandle::tryFrom(self::withSuffix($base, (string) $number))) instanceof ProfileHandle) {
                yield $handle;
            }
        }

        yield from self::random($base);
    }

    /**
     * Whether a handle is what this public name gives (core#740): its slug, a numbered or random-suffixed
     * form of it or, without a public name, a random one. A handle that still follows the name is kept, so
     * touching the name up (`Ana` → `Ana.`) never burns an address.
     */
    public static function follows(string $handle, ?string $publicName): bool
    {
        $base = self::baseOf($publicName);

        if ($base === '') {
            return self::isRandom($handle);
        }

        if ($handle === $base) {
            return true;
        }

        for ($number = 2; $number <= self::NUMBERED_TRIES; $number++) {
            if ($handle === self::withSuffix($base, (string) $number)) {
                return true;
            }
        }

        return preg_match('/^'.preg_quote(self::cut($base, ProfileHandle::MAX_LENGTH - self::RANDOM_LENGTH - 1), '/').'-['.self::RANDOM_ALPHABET.']{'.self::RANDOM_LENGTH.'}$/', $handle) === 1;
    }

    /**
     * Whether a handle is one of the random ones given without a public name.
     */
    public static function isRandom(string $handle): bool
    {
        return preg_match('/^'.self::RANDOM_PREFIX.'-['.self::RANDOM_ALPHABET.']{'.self::RANDOM_LENGTH.'}$/', $handle) === 1;
    }

    /**
     * The slug of the public name, cut to the longest handle and never ending with a hyphen. Empty when
     * there is nothing to slug.
     */
    public static function baseOf(?string $publicName): string
    {
        if ($publicName === null || trim($publicName) === '') {
            return '';
        }

        return self::cut(Str::slug($publicName), ProfileHandle::MAX_LENGTH);
    }

    /**
     * @return Generator<int, ProfileHandle>
     */
    private static function random(string $base): Generator
    {
        for ($try = 0; $try < self::RANDOM_TRIES; $try++) {
            if (($handle = ProfileHandle::tryFrom(self::withSuffix($base, self::randomSuffix()))) instanceof ProfileHandle) {
                yield $handle;
            }
        }
    }

    private static function withSuffix(string $base, string $suffix): string
    {
        return self::cut($base, ProfileHandle::MAX_LENGTH - strlen($suffix) - 1).'-'.$suffix;
    }

    private static function cut(string $slug, int $length): string
    {
        return rtrim(substr($slug, 0, $length), '-');
    }

    private static function randomSuffix(): string
    {
        $last = strlen(self::RANDOM_ALPHABET) - 1;
        $suffix = '';

        for ($i = 0; $i < self::RANDOM_LENGTH; $i++) {
            $suffix .= self::RANDOM_ALPHABET[random_int(0, $last)];
        }

        return $suffix;
    }
}
