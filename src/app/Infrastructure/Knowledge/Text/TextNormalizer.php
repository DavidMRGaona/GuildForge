<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Text;

use Normalizer;

/**
 * Lowercases text and strips accents and diaeresis, one character in for one character out,
 * so positions found in the normalized text point at the same characters in the original.
 */
final class TextNormalizer
{
    /** @var array<string, string> */
    private static array $characters = [];

    public static function normalize(string $text): string
    {
        if (preg_match('/[^\x00-\x7F]/', $text) !== 1) {
            return strtolower($text);
        }

        $normalized = '';
        foreach (mb_str_split($text) as $character) {
            $normalized .= self::$characters[$character] ??= self::character($character);
        }

        return $normalized;
    }

    private static function character(string $character): string
    {
        $lower = mb_strtolower($character);
        $decomposed = Normalizer::normalize($lower, Normalizer::FORM_D);
        $base = preg_replace('/\p{Mn}+/u', '', is_string($decomposed) ? $decomposed : $lower);

        if (is_string($base) && mb_strlen($base) === 1) {
            return $base;
        }

        return mb_strlen($lower) === 1 ? $lower : $character;
    }
}
