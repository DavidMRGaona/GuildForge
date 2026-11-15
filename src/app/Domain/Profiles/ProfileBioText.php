<?php

declare(strict_types=1);

namespace App\Domain\Profiles;

/**
 * The «Sobre mí» text of a profile, ready to draw without any HTML: plain text cut into lines, and
 * each line into text and links. Only `http` and `https` addresses become links (never with a user
 * name before the host, which would pass `https://bank.example@evil.example` for the bank); what
 * the owner wrote stays text whatever it looks like, so the page escapes it like any other text.
 */
final class ProfileBioText
{
    /** Same limit as the links of site blocks */
    public const int MAX_URL_LENGTH = 2048;

    /** Up to the first character that cannot be part of an address written in a sentence */
    private const string URL_PATTERN = '~https?://[^\s<>"\'`]+~iu';

    /** Never part of an address at its end: the sentence's punctuation */
    private const string TRAILING = '.,;:!?\'"';

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
        // Control characters other than tabs and line breaks never reach the page
        $text = (string) preg_replace('/[\x00-\x08\x0B-\x1F\x7F]/u', '', $text);
        $text = trim($text);

        if ($text === '') {
            return [];
        }

        $lines = [];
        $blank = false;

        foreach (explode("\n", $text) as $line) {
            $line = trim($line, " \t");

            if ($line === '') {
                if (! $blank) {
                    $lines[] = [];
                }
                $blank = true;

                continue;
            }

            $blank = false;
            $lines[] = self::segments($line);
        }

        return $lines;
    }

    /**
     * @return list<array{text: string, href: string|null}>
     */
    private static function segments(string $line): array
    {
        $segments = [];
        $text = '';
        $offset = 0;

        preg_match_all(self::URL_PATTERN, $line, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[0] as [$candidate, $position]) {
            $url = self::withoutTrailingPunctuation($candidate);
            $text .= substr($line, $offset, $position - $offset);
            $offset = $position + strlen($url);

            if (! self::isLink($url)) {
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
     * Drops the sentence's punctuation from the end of an address, and a closing bracket that
     * opens nowhere inside it: `(ver https://example.com/a).` links `https://example.com/a`, while
     * `https://es.wikipedia.org/wiki/Rol_(juego)` keeps its own bracket.
     */
    private static function withoutTrailingPunctuation(string $url): string
    {
        while ($url !== '') {
            $last = $url[-1];

            if (str_contains(self::TRAILING, $last)) {
                $url = substr($url, 0, -1);

                continue;
            }

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
        if (strlen($url) > self::MAX_URL_LENGTH) {
            return false;
        }

        $parts = parse_url($url);

        return is_array($parts)
            && in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            && ($parts['host'] ?? '') !== ''
            && ! isset($parts['user'])
            && ! isset($parts['pass']);
    }
}
