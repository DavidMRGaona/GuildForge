<?php

declare(strict_types=1);

namespace App\Domain\Profiles;

/**
 * The public name (`users.display_name`): how the site shows an account to other people (core#855).
 * The account name (`name`) is never published, so a public name is always chosen by the person;
 * the most the site does is suggest the first word of the account name, which never carries a
 * surname, for the person to keep, change or replace.
 */
final class PublicName
{
    /** Length of the `display_name` column */
    public const int MAX_LENGTH = 255;

    /** Spaces of any kind (no-break and wide ones included), tabs and line breaks */
    private const string BLANKS = '[\s\p{Z}]';

    /**
     * Whether an account has no public name: none at all, or nothing but blanks.
     */
    public static function isMissing(?string $value): bool
    {
        return $value === null || preg_match('/^'.self::BLANKS.'*$/u', $value) === 1;
    }

    /**
     * Only the first word of the account name, '' when it has none. Never more: further words are
     * usually surnames, and the person has not agreed to show them.
     */
    public static function suggestFrom(string $accountName): string
    {
        $words = preg_split('/'.self::BLANKS.'+/u', $accountName, -1, PREG_SPLIT_NO_EMPTY);

        if ($words === false || $words === []) {
            return '';
        }

        return mb_substr($words[0], 0, self::MAX_LENGTH);
    }
}
