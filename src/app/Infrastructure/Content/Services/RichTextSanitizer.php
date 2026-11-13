<?php

declare(strict_types=1);

namespace App\Infrastructure\Content\Services;

use App\Application\Content\Services\EditorImageUrlsInterface;
use App\Application\Content\Services\RichTextSanitizerInterface;
use App\Domain\Content\Enums\EditorImageOrigin;
use App\Domain\Content\ValueObjects\EditorImageFolder;
use App\Infrastructure\Support\SanitizesHtml;

final readonly class RichTextSanitizer implements RichTextSanitizerInterface
{
    use SanitizesHtml;

    public function __construct(
        private EditorImageUrlsInterface $urls,
        private EditorImageTags $tags,
    ) {}

    public function sanitize(string $html, EditorImageOrigin $origin): string
    {
        $allowed = $this->imagesOf($html, $origin->folder());

        $clean = $this->sanitizeHtml($html);

        // The purifier drops "data-id", which the panel editor needs to know an image as an attachment:
        // put it back, only on images that are the origin's own, and remove every other image
        $clean = preg_replace('~(?:<p>\s*</p>)+$~', '', $clean) ?? $clean;

        return $this->tags->replace($clean, function (string $tag, ?string $src) use ($allowed): string {
            $id = $src !== null ? ($allowed[$src] ?? null) : null;

            return $id === null ? '' : $this->tags->rewrite($tag, [$tag => ['src' => $src, 'id' => $id]]);
        });
    }

    /**
     * @return array<string, string> delivery URL => stored path, of the images the HTML declares
     *                               with a path in the origin's editor folder
     */
    private function imagesOf(string $html, EditorImageFolder $folder): array
    {
        $images = [];

        foreach ($this->tags->read($html) as $image) {
            $id = $image['id'];

            if ($id === null || ! EditorImageFolder::ofPath($id) instanceof EditorImageFolder
                || ! EditorImageFolder::ofPath($id)->equals($folder)) {
                continue;
            }

            $url = $this->urls->urlFor($id);

            if ($url !== null) {
                $images[$url] = $id;
            }
        }

        return $images;
    }
}
