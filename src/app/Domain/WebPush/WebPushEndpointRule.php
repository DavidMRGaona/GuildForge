<?php

declare(strict_types=1);

namespace App\Domain\WebPush;

/**
 * Which push endpoints the site sends messages to (core#803). The server posts to the address a
 * browser hands it, so anything else would let a request make the site call any host: only https
 * addresses of the browsers' push services, on the default port, without user info or fragment.
 * An installation adds others through `WEB_PUSH_EXTRA_HOSTS`.
 */
final readonly class WebPushEndpointRule
{
    public const int MAX_LENGTH = 2048;

    /** Exact hosts, or `*.` for any subdomain (never the bare domain itself). */
    public const array DEFAULT_HOSTS = [
        'fcm.googleapis.com',
        'updates.push.services.mozilla.com',
        '*.push.apple.com',
        '*.notify.windows.com',
    ];

    private const string HOST_PATTERN = '/^(\*\.)?([a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,63}\z/';

    /** @var list<string> */
    private array $hosts;

    /**
     * @param  list<string>  $extraHosts  Extra hosts in the same notation; anything else is ignored.
     */
    public function __construct(array $extraHosts = [])
    {
        $valid = array_filter(
            array_map(static fn (string $host): string => strtolower(trim($host)), $extraHosts),
            static fn (string $host): bool => preg_match(self::HOST_PATTERN, $host) === 1,
        );

        $this->hosts = array_values(array_unique([...self::DEFAULT_HOSTS, ...$valid]));
    }

    /**
     * The hosts of a comma separated setting, in lower case, empty entries dropped.
     *
     * @return list<string>
     */
    public static function parseHosts(string $setting): array
    {
        return array_values(array_filter(
            array_map(static fn (string $host): string => strtolower(trim($host)), explode(',', $setting)),
            static fn (string $host): bool => $host !== '',
        ));
    }

    public function allows(string $endpoint): bool
    {
        if (strlen($endpoint) > self::MAX_LENGTH || preg_match('/[\s\p{Cc}]/u', $endpoint) === 1) {
            return false;
        }

        $parts = parse_url($endpoint);

        if ($parts === false
            || ($parts['scheme'] ?? null) !== 'https'
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['fragment'])
            || (isset($parts['port']) && $parts['port'] !== 443)
            || ! isset($parts['host'])) {
            return false;
        }

        $host = strtolower($parts['host']);

        return array_any($this->hosts, fn ($allowed) => str_starts_with($allowed, '*.')
            ? str_ends_with($host, substr($allowed, 1)) && strlen($host) > strlen($allowed) - 1
            : $host === $allowed);
    }

    /**
     * The push service of an endpoint, for logs: the rest of the address identifies the browser.
     */
    public static function serviceOf(string $endpoint): ?string
    {
        $host = parse_url($endpoint, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? strtolower($host) : null;
    }
}
