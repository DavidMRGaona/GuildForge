<?php

declare(strict_types=1);

namespace App\Domain\Footer;

/**
 * The settings of a `core.logos` footer block (core#698), read tolerantly: the row is JSON that may
 * have been written outside the panel, so anything that is not a list of logos with an image is ignored.
 */
final class FooterLogos
{
    public const string BLOCK_KEY = 'core.logos';

    public const string FIELD = 'logos';

    /** Folder the block uploads to, with its trailing slash */
    public const string DIRECTORY = 'footer/logos';

    public const int MAX_LOGOS = 8;

    public const int MAX_ALT_LENGTH = 160;

    /**
     * Every logo with an image, in order, with the text and link exactly as stored.
     *
     * @param  array<array-key, mixed>  $settings
     * @return list<array{image: string, alt: string, url: string}>
     */
    public static function itemsIn(array $settings): array
    {
        $stored = $settings[self::FIELD] ?? null;
        $items = [];

        foreach (is_array($stored) ? array_values($stored) : [] as $item) {
            $image = is_array($item) ? self::string($item['image'] ?? null) : '';

            if (! self::isStoredPath($image)) {
                continue;
            }

            $items[] = [
                'image' => $image,
                'alt' => self::string($item['alt'] ?? null),
                'url' => self::string($item['url'] ?? null),
            ];

            if (count($items) === self::MAX_LOGOS) {
                break;
            }
        }

        return $items;
    }

    /**
     * The stored paths of the images.
     *
     * @param  array<array-key, mixed>  $settings
     * @return list<string>
     */
    public static function pathsIn(array $settings): array
    {
        return array_map(static fn (array $item): string => $item['image'], self::itemsIn($settings));
    }

    /**
     * A path of the images disk: no address, no leading slash and no way out of the disk.
     */
    private static function isStoredPath(string $path): bool
    {
        return $path !== ''
            && ! str_starts_with($path, '/')
            && ! str_contains($path, '..')
            && ! str_contains($path, '://')
            && preg_match('/[\x00-\x1f\x7f\\\\]/', $path) !== 1;
    }

    private static function string(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }
}
