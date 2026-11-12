<?php

declare(strict_types=1);

namespace App\Domain\Security;

/**
 * An origin a module may add to the page's Content Security Policy: https, a host name with a
 * top-level domain, an optional port, nothing else. Never a wildcard, a bare scheme, a keyword
 * ('self', 'unsafe-inline'…), an address, a path or another scheme, so a module can only open
 * what it names exactly.
 */
final readonly class ContentSecurityOrigin
{
    private const string PATTERN = '/^https:\/\/((?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z](?:[a-z0-9-]{0,61}[a-z0-9])?)(?::([1-9]\d{0,4}))?$/D';

    private function __construct(
        public string $value,
    ) {}

    /**
     * The origin, or null when the value is not one written exactly (lowercase, no trailing
     * slash).
     */
    public static function tryFrom(string $value): ?self
    {
        if (strlen($value) > 300 || preg_match(self::PATTERN, $value, $parts) !== 1) {
            return null;
        }

        $port = isset($parts[2]) ? (int) $parts[2] : null;
        if ($port !== null && $port > 65535) {
            return null;
        }

        return new self($value);
    }
}
