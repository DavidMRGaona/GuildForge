<?php

declare(strict_types=1);

namespace Tests\Unit\Resources;

use App\Domain\ValueObjects\NeutralPalette;
use GdImage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The core's own icons, served when a site has no favicon of its own (core#807): the plain ones
 * are truly transparent around the logo, and the ones drawn on a background (iOS home screen,
 * maskable) sit on one flat colour. A checkerboard painted by an image editor fails both.
 */
final class CoreFaviconsTest extends TestCase
{
    private const string DIRECTORY = __DIR__.'/../../../public/favicons';

    /** Icons drawn on a transparent background, by size. */
    private const array TRANSPARENT = [
        'light/android-chrome-192x192.png' => 192,
        'light/android-chrome-512x512.png' => 512,
        'light/favicon-16x16.png' => 16,
        'light/favicon-32x32.png' => 32,
        'light/favicon.ico' => null,
        'dark/android-chrome-192x192.png' => 192,
        'dark/android-chrome-512x512.png' => 512,
        'dark/favicon-16x16.png' => 16,
        'dark/favicon-32x32.png' => 32,
        'dark/favicon.ico' => null,
    ];

    /** Icons drawn on an opaque background, with its size and the neutral shade it uses. */
    private const array OPAQUE = [
        'light/apple-touch-icon.png' => [180, 50],
        'light/maskable-192x192.png' => [192, 50],
        'light/maskable-512x512.png' => [512, 50],
        'dark/apple-touch-icon.png' => [180, 950],
    ];

    public function test_every_icon_in_the_folders_is_checked(): void
    {
        $files = array_map(
            fn (string $path): string => substr($path, strlen(self::DIRECTORY) + 1),
            glob(self::DIRECTORY.'/*/*.{png,ico}', GLOB_BRACE) ?: [],
        );
        sort($files);

        $checked = [...array_keys(self::TRANSPARENT), ...array_keys(self::OPAQUE)];
        sort($checked);

        $this->assertSame($checked, $files);
    }

    #[DataProvider('transparentIcons')]
    public function test_a_plain_icon_is_transparent_all_around_the_logo(string $file, ?int $size): void
    {
        foreach ($this->images($file) as $frame => $image) {
            $width = imagesx($image);

            if ($size !== null) {
                $this->assertSame([$size, $size], [$width, imagesy($image)], "{$file} has the wrong size");
            }

            $this->assertNull(
                $this->firstPixel($this->border($image, 1), fn (int $x, int $y): bool => $this->alpha($image, $x, $y) !== 127),
                "{$file} (frame {$frame}, {$width} px) is not transparent along its edges",
            );
        }
    }

    #[DataProvider('opaqueIcons')]
    public function test_an_icon_on_a_background_has_one_flat_opaque_colour_around_the_logo(string $file, int $size, int $shade): void
    {
        $image = $this->images($file)[0];
        $background = strtolower(NeutralPalette::SHADES[$shade]);

        $this->assertSame([$size, $size], [imagesx($image), imagesy($image)], "{$file} has the wrong size");

        $this->assertNull(
            $this->firstPixel($this->border($image, intdiv($size, 2)), fn (int $x, int $y): bool => $this->alpha($image, $x, $y) !== 0),
            "{$file} is not opaque",
        );
        $this->assertNull(
            $this->firstPixel($this->border($image, intdiv($size, 12)), fn (int $x, int $y): bool => $this->colour($image, $x, $y) !== $background),
            "{$file} is not {$background} around the logo",
        );
    }

    /**
     * @return iterable<string, array{string, ?int}>
     */
    public static function transparentIcons(): iterable
    {
        foreach (self::TRANSPARENT as $file => $size) {
            yield $file => [$file, $size];
        }
    }

    /**
     * @return iterable<string, array{string, int, int}>
     */
    public static function opaqueIcons(): iterable
    {
        foreach (self::OPAQUE as $file => [$size, $shade]) {
            yield $file => [$file, $size, $shade];
        }
    }

    /**
     * The PNG, or every image of a Windows icon.
     *
     * @return list<GdImage>
     */
    private function images(string $file): array
    {
        $contents = file_get_contents(self::DIRECTORY.'/'.$file);
        $this->assertIsString($contents, "{$file} cannot be read");

        if (! str_ends_with($file, '.ico')) {
            return [$this->png($contents)];
        }

        $header = unpack('vreserved/vtype/vcount', $contents);
        $this->assertIsArray($header);
        $this->assertSame([0, 1], [$header['reserved'], $header['type']], "{$file} is not an icon");
        $this->assertGreaterThan(0, $header['count'], "{$file} holds no image");

        $images = [];
        for ($i = 0; $i < $header['count']; $i++) {
            $entry = unpack('Vsize/Voffset', $contents, 6 + 16 * $i + 8);
            $this->assertIsArray($entry);
            $data = substr($contents, $entry['offset'], $entry['size']);
            $images[] = str_starts_with($data, "\x89PNG") ? $this->png($data) : $this->bitmap($data, $file);
        }

        return $images;
    }

    private function png(string $data): GdImage
    {
        $image = imagecreatefromstring($data);

        if (! $image instanceof GdImage) {
            throw new RuntimeException('Unreadable PNG');
        }

        return $image;
    }

    /**
     * A 32-bit BMP image of an icon: BGRA rows from the bottom, twice as tall as the image (the
     * second half is the 1-bit mask, which the alpha channel already covers).
     */
    private function bitmap(string $data, string $file): GdImage
    {
        $header = unpack('VheaderSize/lwidth/lheight/vplanes/vbits', $data);
        $this->assertIsArray($header);
        $this->assertSame(32, $header['bits'], "{$file} holds an image without an alpha channel");

        $width = $header['width'];
        $height = max(1, intdiv($header['height'], 2));
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $pixel = unpack('Cb/Cg/Cr/Ca', $data, $header['headerSize'] + (($height - 1 - $y) * $width + $x) * 4);
                $this->assertIsArray($pixel);
                // A true colour GD pixel: 7-bit alpha (127 transparent), then red, green and blue.
                $alpha = 127 - ($pixel['a'] >> 1);
                imagesetpixel($image, $x, $y, ($alpha << 24) | ($pixel['r'] << 16) | ($pixel['g'] << 8) | $pixel['b']);
            }
        }

        return $image;
    }

    /**
     * Every pixel within `$depth` of an edge.
     *
     * @return iterable<array{int, int}>
     */
    private function border(GdImage $image, int $depth): iterable
    {
        $width = imagesx($image);
        $height = imagesy($image);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if ($x < $depth || $y < $depth || $x >= $width - $depth || $y >= $height - $depth) {
                    yield [$x, $y];
                }
            }
        }
    }

    /**
     * The first pixel that matches, as "x,y", or null.
     *
     * @param  iterable<array{int, int}>  $pixels
     * @param  callable(int, int): bool  $matches
     */
    private function firstPixel(iterable $pixels, callable $matches): ?string
    {
        foreach ($pixels as [$x, $y]) {
            if ($matches($x, $y)) {
                return "{$x},{$y}";
            }
        }

        return null;
    }

    /** GD's alpha: 0 opaque, 127 transparent. */
    private function alpha(GdImage $image, int $x, int $y): int
    {
        return (imagecolorat($image, $x, $y) >> 24) & 0x7F;
    }

    private function colour(GdImage $image, int $x, int $y): string
    {
        return sprintf('#%06x', imagecolorat($image, $x, $y) & 0xFFFFFF);
    }
}
