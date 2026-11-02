<?php

declare(strict_types=1);

namespace App\Infrastructure\Metrics;

use App\Infrastructure\Knowledge\Text\TextNormalizer;

/**
 * Turns a search query into what anonymous metrics may store: lower case, no accents, single
 * spaces, at most 100 characters. A query that looks like personal data (an email address or
 * a phone number) or is too short to mean anything is never kept.
 */
final class AnonymousQueryNormalizer
{
    private const int MAX_LENGTH = 100;

    private const int MIN_LENGTH = 2;

    private const int PHONE_DIGITS = 7;

    public static function normalize(string $query): ?string
    {
        $collapsed = trim((string) preg_replace('/\s+/u', ' ', TextNormalizer::normalize($query)));

        if (str_contains($collapsed, '@')) {
            return null;
        }

        if (preg_match_all('/\d/', $collapsed) >= self::PHONE_DIGITS) {
            return null;
        }

        $trimmed = trim(mb_substr($collapsed, 0, self::MAX_LENGTH));

        return mb_strlen($trimmed) < self::MIN_LENGTH ? null : $trimmed;
    }
}
