<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Rendering;

/**
 * Where an action button leads: a route name and, after `?`, query parameters such as
 * `profile.show?tab=surveys`. Shared by the renderer (which builds the URL) and the linter
 * (which validates it).
 */
final class ActionTarget
{
    private const string QUERY = '/^[A-Za-z0-9_.-]+=[A-Za-z0-9_.-]*(?:&[A-Za-z0-9_.-]+=[A-Za-z0-9_.-]*)*$/';

    /**
     * @return array{route: string, query: string|null}
     */
    public static function split(string $target): array
    {
        $parts = explode('?', $target, 2);

        return ['route' => trim($parts[0]), 'query' => isset($parts[1]) ? trim($parts[1]) : null];
    }

    /**
     * The query as route parameters, or null when it is not a list of `key=value` pairs of
     * letters, digits, dots, hyphens and underscores.
     *
     * @return array<string, string>|null
     */
    public static function parameters(?string $query): ?array
    {
        if ($query === null) {
            return [];
        }

        if (preg_match(self::QUERY, $query) !== 1) {
            return null;
        }

        $parameters = [];
        foreach (explode('&', $query) as $pair) {
            [$key, $value] = explode('=', $pair, 2);
            $parameters[$key] = $value;
        }

        return $parameters;
    }
}
