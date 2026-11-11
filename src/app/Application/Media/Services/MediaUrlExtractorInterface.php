<?php

declare(strict_types=1);

namespace App\Application\Media\Services;

use RuntimeException;

/**
 * Finds the files of this site named in a stored text: every delivery URL in an attribute (src, href,
 * srcset) or as bare text, whatever its transformation or version, in HTML or JSON-escaped HTML. What a
 * media source that reads rich text needs (RichTextMediaSource and the modules' sources), core#496.
 */
interface MediaUrlExtractorInterface
{
    /**
     * Files of this site the text points to, by file id, each with the first URL that names it. Anything that
     * is not a non-empty string names nothing.
     *
     * @return array<string, string>
     *
     * @throws RuntimeException when the text cannot be read: it never looks as if it named nothing, so the
     *                          caller fails and a deletion is refused
     */
    public function fileIdsIn(mixed $text): array;
}
