<?php

declare(strict_types=1);

namespace App\Infrastructure\Search\Support;

/**
 * Turns stored HTML into the plain text the search index holds.
 */
final class PlainText
{
    private const string BLOCK_TAGS = '/<\/?(?:p|div|br|hr|li|ul|ol|h[1-6]|table|thead|tbody|tr|td|th|blockquote|pre|section|article|figure|figcaption)\b[^>]*>/i';

    public static function from(string $html): string
    {
        // Block tags become a space first, so "<p>a</p><p>b</p>" does not glue into "ab"
        $spaced = preg_replace(self::BLOCK_TAGS, ' ', $html) ?? $html;
        $text = html_entity_decode(strip_tags($spaced), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $text) ?? $text);
    }
}
