<?php

declare(strict_types=1);

namespace App\Application\Social;

/**
 * Turns the address stored for a social network (saved without `https://`) into a
 * safe https URL, or an empty string when what is stored cannot be one.
 */
final class SocialUrl
{
    private const string HOST_PATTERN = '/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i';

    public static function normalize(mixed $stored, ?SocialNetwork $network = null): string
    {
        if (! is_string($stored)) {
            return '';
        }

        $value = trim($stored);

        if ($value === '') {
            return '';
        }

        $withoutScheme = preg_replace('#^https?://#i', '', $value) ?? $value;
        $url = 'https://'.$withoutScheme;

        return self::isSafe($url, $network) ? $url : '';
    }

    public static function isValid(string $address, ?SocialNetwork $network = null): bool
    {
        return self::normalize($address, $network) !== '';
    }

    private static function isSafe(string $url, ?SocialNetwork $network): bool
    {
        if (strlen($url) > 2048 || preg_match('/[\s<>"\'\\\\\x00-\x1f\x7f]/', $url) === 1) {
            return false;
        }

        $parts = parse_url($url);

        if ($parts === false || ($parts['scheme'] ?? '') !== 'https') {
            return false;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $host = strtolower($parts['host'] ?? '');

        if ($host === '' || preg_match(self::HOST_PATTERN, $host) !== 1) {
            return false;
        }

        $allowed = $network?->allowedHosts();

        return $allowed === null || in_array($host, $allowed, true);
    }
}
