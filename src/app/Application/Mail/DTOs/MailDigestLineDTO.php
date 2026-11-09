<?php

declare(strict_types=1);

namespace App\Application\Mail\DTOs;

use InvalidArgumentException;

/**
 * What a notice says in the daily digest (core#333): one short line and, optionally, where to
 * look. The text is written by people (names of games, of players...): it is escaped wherever it
 * is shown, so pass it as it is.
 */
final readonly class MailDigestLineDTO
{
    public const int MAX_LENGTH = 200;

    public const int URL_MAX_LENGTH = 2048;

    /** On one line and at most MAX_LENGTH characters, cut with «…» when longer. */
    public string $text;

    /**
     * @param  string|null  $url  Absolute http(s) address the line links to.
     *
     * @throws InvalidArgumentException When the text is empty or the address cannot be linked.
     */
    public function __construct(string $text, public ?string $url = null)
    {
        $this->text = $this->normalize($text);

        if ($url !== null && ! $this->isLinkable($url)) {
            throw new InvalidArgumentException('The digest line links to an address that is not an absolute http(s) URL.');
        }
    }

    private function normalize(string $text): string
    {
        // One line, no control or invisible formatting characters
        $oneLine = preg_replace(['/\s+/u', '/[\p{Cc}\p{Cf}]/u'], [' ', ''], $text);

        if ($oneLine === null) {
            throw new InvalidArgumentException('The digest line is not valid UTF-8.');
        }

        $oneLine = trim($oneLine);

        if ($oneLine === '') {
            throw new InvalidArgumentException('The digest line is empty.');
        }

        return mb_strlen($oneLine) > self::MAX_LENGTH
            ? rtrim(mb_substr($oneLine, 0, self::MAX_LENGTH - 1)).'…'
            : $oneLine;
    }

    private function isLinkable(string $url): bool
    {
        if (strlen($url) > self::URL_MAX_LENGTH || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        return is_string($scheme) && in_array(strtolower($scheme), ['http', 'https'], true);
    }
}
