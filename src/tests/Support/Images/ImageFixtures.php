<?php

declare(strict_types=1);

namespace Tests\Support\Images;

use RuntimeException;

/**
 * Tiny, real images built byte by byte: every decoder reads them, and each one has
 * exactly the frames it is asked for.
 */
final class ImageFixtures
{
    /** A 1x1 frame coded with a 2-colour table (classic smallest GIF pixel). */
    private const string GIF_FRAME_DATA = "\x02\x02\x44\x01\x00";

    /**
     * A 1x1 GIF with $frames image descriptors.
     *
     * The default colour table ends with a non-zero byte, so the first Graphic Control
     * Extension is not preceded by 0x00 when there is no loop block (what a byte-pattern
     * count used to miss).
     */
    public static function gif(int $frames, bool $graphicControl = true, string $colorTable = "\x00\x00\x00\xFF\xFF\xFF", bool $loop = true): string
    {
        // Logical screen: 1x1, global colour table of 2 entries
        $gif = 'GIF89a'.pack('vv', 1, 1)."\x80\x00\x00".$colorTable;

        if ($frames > 1 && $loop) {
            $gif .= "\x21\xFF\x0BNETSCAPE2.0\x03\x01\x00\x00\x00";
        }

        for ($frame = 0; $frame < $frames; $frame++) {
            if ($graphicControl) {
                $gif .= "\x21\xF9\x04\x04\x0A\x00\x00\x00";
            }

            $gif .= "\x2C".pack('vvvv', 0, 0, 1, 1)."\x00".self::GIF_FRAME_DATA;
        }

        return $gif."\x3B";
    }

    /**
     * A 1x1 PNG; with $frames > 0 an APNG with an acTL chunk announcing them.
     *
     * $animationControlAfterData puts the acTL chunk after IDAT, where decoders ignore it.
     */
    public static function png(int $frames = 0, bool $animationControlAfterData = false): string
    {
        $png = "\x89PNG\r\n\x1A\n".self::pngChunk('IHDR', pack('NN', 1, 1)."\x08\x02\x00\x00\x00");
        $animationControl = $frames > 0 ? self::pngChunk('acTL', pack('NN', $frames, 0)) : '';
        $sequence = 0;

        if (! $animationControlAfterData) {
            $png .= $animationControl;
        }

        if ($frames > 0) {
            $png .= self::pngChunk('fcTL', self::pngFrameControl($sequence++));
        }

        $png .= self::pngChunk('IDAT', self::pngPixel("\xFF\x00\x00"));

        if ($animationControlAfterData) {
            $png .= $animationControl;
        }

        for ($frame = 1; $frame < $frames; $frame++) {
            $png .= self::pngChunk('fcTL', self::pngFrameControl($sequence++));
            $png .= self::pngChunk('fdAT', pack('N', $sequence++).self::pngPixel("\x00\x00\xFF"));
        }

        return $png.self::pngChunk('IEND', '');
    }

    /**
     * A 1x1 WebP: the simple format when $frames is 0, otherwise the extended format with
     * the animation flag and $frames ANMF chunks.
     */
    public static function webp(int $frames = 0): string
    {
        $simple = self::gdWebp();

        if ($frames === 0) {
            return $simple;
        }

        // The VP8/VP8L chunk of the simple file is the bitstream of every frame
        $bitstream = substr($simple, 12);
        $chunks = self::riffChunk('VP8X', "\x02\x00\x00\x00".self::uint24(0).self::uint24(0))
            .self::riffChunk('ANIM', "\x00\x00\x00\x00".pack('v', 0));

        for ($frame = 0; $frame < $frames; $frame++) {
            $chunks .= self::riffChunk(
                'ANMF',
                self::uint24(0).self::uint24(0).self::uint24(0).self::uint24(0).self::uint24(100)."\x00".$bitstream,
            );
        }

        return 'RIFF'.pack('V', 4 + strlen($chunks)).'WEBP'.$chunks;
    }

    public static function jpeg(): string
    {
        $image = imagecreatetruecolor(1, 1);
        ob_start();
        imagejpeg($image);

        return (string) ob_get_clean();
    }

    private static function gdWebp(): string
    {
        $image = imagecreatetruecolor(1, 1);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 255, 0, 0));
        ob_start();
        imagewebp($image, null, 80);
        $webp = (string) ob_get_clean();

        if (! str_starts_with($webp, 'RIFF') || substr($webp, 8, 4) !== 'WEBP') {
            throw new RuntimeException('GD did not produce a WebP image');
        }

        return $webp;
    }

    private static function pngChunk(string $type, string $data): string
    {
        return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
    }

    private static function pngFrameControl(int $sequence): string
    {
        // sequence, width, height, x, y, delay 1/10 s, dispose none, blend source
        return pack('NNNNNnnCC', $sequence, 1, 1, 0, 0, 1, 10, 0, 0);
    }

    private static function pngPixel(string $rgb): string
    {
        // One scanline: filter type 0 and the pixel
        return (string) gzcompress("\x00".$rgb);
    }

    private static function riffChunk(string $fourCc, string $payload): string
    {
        $size = strlen($payload);

        return $fourCc.pack('V', $size).$payload.($size % 2 === 1 ? "\x00" : '');
    }

    private static function uint24(int $value): string
    {
        return substr(pack('V', $value), 0, 3);
    }
}
