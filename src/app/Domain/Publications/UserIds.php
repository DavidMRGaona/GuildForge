<?php

declare(strict_types=1);

namespace App\Domain\Publications;

use InvalidArgumentException;

/**
 * Account ids in publication rosters: UUIDs, kept in lowercase and once each.
 */
final class UserIds
{
    private const string UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

    /**
     * @param  array<mixed>  $ids
     * @return list<string>
     *
     * @throws InvalidArgumentException when an id is not a UUID
     */
    public static function normalize(array $ids, string $context): array
    {
        $normalized = [];

        foreach ($ids as $id) {
            $id = is_string($id) ? strtolower(trim($id)) : '';

            if (preg_match(self::UUID_PATTERN, $id) !== 1) {
                throw new InvalidArgumentException("A publication {$context} holds an id that is not an account id.");
            }

            $normalized[$id] = true;
        }

        return array_keys($normalized);
    }
}
