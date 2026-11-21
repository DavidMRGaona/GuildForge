<?php

declare(strict_types=1);

namespace App\Domain\Activity;

/**
 * The ids activity facts carry (core#774). Consumers store them and compare them for years, so they
 * must be stable: a subject id and an attribute value id are a record's own id (a UUID, a number, a
 * slug), never a title; attribute keys are snake_case; people are account UUIDs, kept in lower case.
 * Patterns are read whole: nothing, not even a final newline, may follow.
 */
final class ActivityIdentifierRule
{
    public const int MAX_ID_LENGTH = 191;

    public const int MAX_ATTRIBUTE_KEY_LENGTH = 64;

    private const string STABLE_ID_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._:-]*$/D';

    private const string ATTRIBUTE_KEY_PATTERN = '/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/D';

    private const string UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D';

    /** A subject id or an attribute value id: letters, digits, `.`, `_`, `:` and `-`, starting with a letter or digit */
    public static function isStableId(string $id): bool
    {
        return strlen($id) <= self::MAX_ID_LENGTH && preg_match(self::STABLE_ID_PATTERN, $id) === 1;
    }

    /** An attribute key, e.g. `game_system` */
    public static function isAttributeKey(string $key): bool
    {
        return strlen($key) <= self::MAX_ATTRIBUTE_KEY_LENGTH && preg_match(self::ATTRIBUTE_KEY_PATTERN, $key) === 1;
    }

    /** The account id in lower case, or null when it is not a UUID */
    public static function userId(string $id): ?string
    {
        $id = strtolower(trim($id));

        return preg_match(self::UUID_PATTERN, $id) === 1 ? $id : null;
    }
}
