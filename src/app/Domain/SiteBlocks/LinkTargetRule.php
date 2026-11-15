<?php

declare(strict_types=1);

namespace App\Domain\SiteBlocks;

/**
 * Where a link of a site block may point (core#698): an absolute `https` address or a path of this
 * site. Anything else is refused: `javascript:` and other schemes, `//host` (the browser reads it as
 * another site), backslashes (some browsers read `/\host` as `//host`), whitespace and control
 * characters, addresses with a user name, and `http` unless the caller allows it (local and testing).
 */
final class LinkTargetRule
{
    public const int MAX_LENGTH = 2048;

    /**
     * The link as it can be used, or null when it is not allowed.
     */
    public static function normalize(mixed $value, bool $allowInsecure = false): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $url = trim($value);

        if ($url === '' || strlen($url) > self::MAX_LENGTH || preg_match('/[\x00-\x20\x7f\\\\]/', $url) === 1) {
            return null;
        }

        if ($url[0] === '/') {
            return ($url[1] ?? '') === '/' ? null : $url;
        }

        if (preg_match('#^(https?)://#i', $url, $scheme) !== 1) {
            return null;
        }

        if (strtolower($scheme[1]) === 'http' && ! $allowInsecure) {
            return null;
        }

        $parts = parse_url($url);

        if ($parts === false || ($parts['host'] ?? '') === '' || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        return $url;
    }
}
