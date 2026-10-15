<?php

declare(strict_types=1);

namespace App\Infrastructure\Images;

/**
 * Tells whether an image holds more than one frame, reading its bytes only (the MIME
 * type or name an upload claims is never trusted).
 *
 * - GIF: more than one image descriptor, found by walking the block structure (not by
 *   searching byte patterns, which miss frames whose blocks follow a non-zero byte).
 * - PNG (APNG): an acTL chunk before the first IDAT announcing more than one frame.
 * - WebP: more than one ANMF (animation frame) chunk.
 *
 * Truncated or malformed data never throws: what was read before the damage decides.
 */
final class AnimatedImageDetector
{
    private const string PNG_SIGNATURE = "\x89PNG\r\n\x1A\n";

    public function isAnimated(string $contents): bool
    {
        if (str_starts_with($contents, 'GIF87a') || str_starts_with($contents, 'GIF89a')) {
            return $this->gifFrames($contents) > 1;
        }

        if (str_starts_with($contents, self::PNG_SIGNATURE)) {
            return $this->apngFrames($contents) > 1;
        }

        if (str_starts_with($contents, 'RIFF') && substr($contents, 8, 4) === 'WEBP') {
            return $this->webpFrames($contents) > 1;
        }

        return false;
    }

    /**
     * Count image descriptors, stopping at the second one: that is enough to know.
     */
    private function gifFrames(string $contents): int
    {
        $length = strlen($contents);

        if ($length < 13) {
            return 0;
        }

        // Header (6) and logical screen descriptor (7), then the global colour table
        $offset = 13 + $this->colorTableSize(ord($contents[10]));
        $frames = 0;

        while ($offset < $length && $frames < 2) {
            $block = ord($contents[$offset]);

            if ($block === 0x21) {
                // Extension: introducer, label, then data sub-blocks
                $offset = $this->skipSubBlocks($contents, $offset + 2);
            } elseif ($block === 0x2C) {
                $frames++;

                if ($offset + 10 > $length) {
                    break;
                }

                // Image descriptor (10), local colour table, LZW minimum code size, data sub-blocks
                $offset += 10 + $this->colorTableSize(ord($contents[$offset + 9]));
                $offset = $this->skipSubBlocks($contents, $offset + 1);
            } else {
                // Trailer (0x3B) or damaged data
                break;
            }
        }

        return $frames;
    }

    private function colorTableSize(int $packedFields): int
    {
        return ($packedFields & 0x80) !== 0 ? 3 * (2 << ($packedFields & 0x07)) : 0;
    }

    private function skipSubBlocks(string $contents, int $offset): int
    {
        $length = strlen($contents);

        while ($offset < $length) {
            $size = ord($contents[$offset]);
            $offset++;

            if ($size === 0) {
                return $offset;
            }

            $offset += $size;
        }

        return $length;
    }

    /**
     * Frames announced by an acTL chunk before the image data; 0 when there is none.
     */
    private function apngFrames(string $contents): int
    {
        $length = strlen($contents);
        $offset = strlen(self::PNG_SIGNATURE);

        while ($offset + 8 <= $length) {
            /** @var array{1: int} $size */
            $size = unpack('N', substr($contents, $offset, 4));
            $type = substr($contents, $offset + 4, 4);

            if ($type === 'IDAT') {
                return 0;
            }

            if ($type === 'acTL') {
                if ($offset + 12 > $length) {
                    return 0;
                }

                /** @var array{1: int} $frames */
                $frames = unpack('N', substr($contents, $offset + 8, 4));

                return $frames[1];
            }

            // Length, type, data and CRC
            $offset += 12 + $size[1];
        }

        return 0;
    }

    /**
     * Count ANMF chunks, stopping at the second one.
     */
    private function webpFrames(string $contents): int
    {
        $length = strlen($contents);
        $offset = 12;
        $frames = 0;

        while ($offset + 8 <= $length && $frames < 2) {
            /** @var array{1: int} $size */
            $size = unpack('V', substr($contents, $offset + 4, 4));

            if (substr($contents, $offset, 4) === 'ANMF') {
                $frames++;
            }

            // FourCC, size and payload, padded to an even length
            $offset += 8 + $size[1] + ($size[1] % 2);
        }

        return $frames;
    }
}
