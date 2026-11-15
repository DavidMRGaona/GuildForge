<?php

declare(strict_types=1);

namespace App\Domain\Profiles;

/**
 * The «Sobre mí» text of a profile, ready to draw without any HTML: plain text cut into lines, and
 * each line into text and links. Only `http` and `https` addresses become links, with a plain host
 * (letters, digits, dots and hyphens; international names in their `xn--` form), never a user name
 * before it (`https://bank.example@evil.example` would pass for the bank) nor a backslash; what the
 * owner wrote stays text whatever it looks like, so the page escapes it like any other text. Control
 * characters (C0 and C1) and direction marks are dropped (they could make a link read as another),
 * and the text is cut to MAX_LENGTH; an address the cut splits stays text.
 */
final class ProfileBioText
{
    /** Characters drawn at most, the limit of the bio in the profile editor */
    public const int MAX_LENGTH = 500;

    /** Up to the first character that cannot be part of an address written in a sentence: spaces, angle brackets and quotes of any kind */
    private const string URL_PATTERN = '~https?://[^\s\p{Z}<>"\'`«»“”„‘’‚‹›]+~iu';

    /** Never part of an address at its end: the sentence's punctuation */
    private const string TRAILING_PATTERN = '/[.,;:!?…]+$/u';

    /** Controls other than tabs and line breaks, and the invisible marks that turn text around */
    private const string INVISIBLE_PATTERN = '/[\x00-\x08\x0B-\x1F\x7F-\x{009F}\x{061C}\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u';

    /** Letters, digits and hyphens in dot-separated labels: an international name only as `xn--` */
    private const string HOST_PATTERN = '/^[a-z0-9-]+(?:\.[a-z0-9-]+)*$/i';

    /**
     * Lines in order, each a list of segments. A blank line is an empty list; blank lines at the
     * start and end are dropped and a run of them counts as one. A segment is a link when `href`
     * is set, and its text is always what the owner wrote.
     *
     * @return list<list<array{text: string, href: string|null}>>
     */
    public static function lines(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        // Invalid UTF-8 makes preg_replace() answer null: nothing is drawn then
        $text = trim((string) preg_replace(self::INVISIBLE_PATTERN, '', $text));
        $cut = mb_substr($text, 0, self::MAX_LENGTH);
        // An address the cut splits would link somewhere else (`bank.example.evil.com` → `bank.example`)
        $tailIsSplit = $cut !== $text && self::splitsAnAddress($text, strlen($cut));
        $text = rtrim($cut);

        if ($text === '') {
            return [];
        }

        $lines = [];
        $blank = false;
        $rows = explode("\n", $text);
        $last = count($rows) - 1;

        foreach ($rows as $index => $line) {
            $line = trim($line, " \t");

            if ($line === '') {
                if (! $blank) {
                    $lines[] = [];
                }
                $blank = true;

                continue;
            }

            $blank = false;
            $lines[] = self::segments($line, $tailIsSplit && $index === $last);
        }

        return $lines;
    }

    /**
     * Whether an address runs across a byte offset of the text, so cutting there would split it.
     */
    private static function splitsAnAddress(string $text, int $offset): bool
    {
        preg_match_all(self::URL_PATTERN, $text, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[0] as [$candidate, $position]) {
            if ($position < $offset && $position + strlen($candidate) > $offset) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  bool  $endIsSplit  the line ends where the text was cut through an address: that one stays text
     * @return list<array{text: string, href: string|null}>
     */
    private static function segments(string $line, bool $endIsSplit = false): array
    {
        $segments = [];
        $text = '';
        $offset = 0;

        preg_match_all(self::URL_PATTERN, $line, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[0] as [$candidate, $position]) {
            $url = self::withoutTrailingPunctuation($candidate);
            $text .= substr($line, $offset, $position - $offset);
            $offset = $position + strlen($url);
            $split = $endIsSplit && $position + strlen($candidate) === strlen($line);

            if ($split || ! self::isLink($url)) {
                $text .= $url;

                continue;
            }

            if ($text !== '') {
                $segments[] = ['text' => $text, 'href' => null];
                $text = '';
            }

            $segments[] = ['text' => $url, 'href' => $url];
        }

        $text .= substr($line, $offset);

        if ($text !== '') {
            $segments[] = ['text' => $text, 'href' => null];
        }

        return $segments;
    }

    /**
     * Drops the sentence's punctuation (an ellipsis too) from the end of an address, and a closing
     * bracket that opens nowhere inside it: `(ver https://example.com/a).` links `https://example.com/a`, while
     * `https://es.wikipedia.org/wiki/Rol_(juego)` keeps its own bracket.
     */
    private static function withoutTrailingPunctuation(string $url): string
    {
        while ($url !== '') {
            $trimmed = (string) preg_replace(self::TRAILING_PATTERN, '', $url);
            if ($trimmed !== $url) {
                $url = $trimmed;

                continue;
            }

            $last = $url[-1];

            $pairs = [')' => '(', ']' => '[', '}' => '{'];
            if (isset($pairs[$last]) && substr_count($url, $pairs[$last]) < substr_count($url, $last)) {
                $url = substr($url, 0, -1);

                continue;
            }

            break;
        }

        return $url;
    }

    private static function isLink(string $url): bool
    {
        if (str_contains($url, '\\')) {
            return false;
        }

        $parts = parse_url($url);

        return is_array($parts)
            && in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            && preg_match(self::HOST_PATTERN, $parts['host'] ?? '') === 1
            && ! isset($parts['user'])
            && ! isset($parts['pass']);
    }
}
