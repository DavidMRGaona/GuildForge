<?php

declare(strict_types=1);

namespace App\Infrastructure\Content\Services;

use RuntimeException;

/**
 * Reads and rewrites the "src" and "data-id" of the <img> tags of stored rich text, leaving every
 * other byte as it was (a parser would rewrite the whole document).
 */
final readonly class EditorImageTags
{
    /** An <img> tag: attribute values may hold ">" */
    private const string TAG = '~<img\b(?:[^>"\']|"[^"]*"|\'[^\']*\')*>~i';

    /** One attribute of a tag, quoted or not */
    private const string ATTRIBUTE = '~\s(%s)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'>]+))~i';

    /**
     * @return list<array{tag: string, src: ?string, id: ?string}> every tag with its decoded attributes
     */
    public function read(string $html): array
    {
        preg_match_all(self::TAG, $html, $matches);

        return array_map(fn (string $tag): array => [
            'tag' => $tag,
            'src' => $this->attribute($tag, 'src'),
            'id' => $this->attribute($tag, 'data-id'),
        ], $matches[0]);
    }

    /**
     * Gives the tags listed in $images (by their exact markup) a new "src" and "data-id"; the
     * "data-id" is added when the tag has none, so the editor knows the image as an attachment.
     *
     * @param  array<string, array{src: string, id: string}>  $images
     */
    public function rewrite(string $html, array $images): string
    {
        return preg_replace_callback(self::TAG, function (array $match) use ($images): string {
            $image = $images[$match[0]] ?? null;

            if ($image === null) {
                return $match[0];
            }

            $tag = $this->set($match[0], 'src', $image['src']);

            return $this->set($tag, 'data-id', $image['id']);
        }, $html) ?? throw new RuntimeException('Could not rewrite the images: '.preg_last_error_msg());
    }

    private function attribute(string $tag, string $name): ?string
    {
        if (preg_match(sprintf(self::ATTRIBUTE, preg_quote($name, '~')), $tag, $match) !== 1) {
            return null;
        }

        $value = ($match[2] ?? '') !== '' ? $match[2] : (($match[3] ?? '') !== '' ? $match[3] : ($match[4] ?? ''));

        return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function set(string $tag, string $name, string $value): string
    {
        $attribute = ' '.$name.'="'.htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8').'"';
        $pattern = sprintf(self::ATTRIBUTE, preg_quote($name, '~'));

        if (preg_match($pattern, $tag) === 1) {
            return (string) preg_replace_callback($pattern, static fn (): string => $attribute, $tag, 1);
        }

        return (string) preg_replace_callback('~\s*/?>$~', static fn (array $end): string => $attribute.$end[0], $tag, 1);
    }
}
