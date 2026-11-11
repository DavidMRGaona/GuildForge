<?php

declare(strict_types=1);

namespace App\Infrastructure\Media\Services;

use App\Application\Media\Services\MediaFileIdResolverInterface;
use App\Application\Media\Services\MediaUrlExtractorInterface;
use App\Domain\Media\ValueObjects\MediaFileId;
use RuntimeException;

final readonly class MediaUrlExtractor implements MediaUrlExtractorInterface
{
    /**
     * Anything URL-like: the resolver decides which ones are files of this site. Quotes, angle brackets,
     * whitespace and backslashes end a URL in HTML and in JSON-escaped HTML.
     */
    private const string URL_PATTERN = '~(?:https?:)?//[^\s"\'<>\\\\]+~i';

    /** Punctuation that may follow a bare URL in a sentence or a srcset list */
    private const string TRAILING_PUNCTUATION = '.,;:!?)]';

    /**
     * The longest start of a match made of characters a URL may hold (RFC 3986, `%` for encoded ones). What
     * follows a bare URL in Spanish text («», “”, a non-breaking space, an ellipsis) is outside it.
     */
    private const string URL_CHARACTERS_PATTERN = '~^[A-Za-z0-9\-._\~:/?#\[\]@!$&\'()*+,;=%]+~';

    public function __construct(
        private MediaFileIdResolverInterface $resolver,
    ) {}

    public function fileIdsIn(mixed $text): array
    {
        if (! is_string($text) || $text === '') {
            return [];
        }

        // JSON-escaped slashes and HTML entities (&amp;, &#47;…) would hide a URL from the pattern
        $decoded = html_entity_decode(str_replace('\\/', '/', $text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // A text that cannot be read must never look as if it used nothing: the caller fails, and a deletion is refused
        if (preg_match_all(self::URL_PATTERN, $decoded, $matches) === false) {
            throw $this->unreadable();
        }

        $found = [];

        foreach ($matches[0] as $match) {
            $url = str_starts_with($match, '//') ? 'https:'.$match : $match;

            if (preg_match(self::URL_CHARACTERS_PATTERN, $url, $plain) === false) {
                throw $this->unreadable();
            }

            $cut = $plain[0] ?? $url;

            // Every form is tried, as more forms only protect more files: a closing mark may be part of the URL
            // or of the sentence around it, and a bare URL without extension ends where URL characters end
            $candidates = array_unique([
                $url,
                rtrim($url, self::TRAILING_PUNCTUATION),
                $cut,
                rtrim($cut, self::TRAILING_PUNCTUATION),
            ]);

            foreach ($candidates as $candidate) {
                $id = $this->resolver->fromStoredPath($candidate);

                if ($id instanceof MediaFileId) {
                    $found[$id->value] ??= $candidate;
                }
            }
        }

        return $found;
    }

    private function unreadable(): RuntimeException
    {
        return new RuntimeException('A text could not be read for media URLs: '.preg_last_error_msg());
    }
}
