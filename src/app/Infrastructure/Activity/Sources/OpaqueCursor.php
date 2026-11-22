<?php

declare(strict_types=1);

namespace App\Infrastructure\Activity\Sources;

use InvalidArgumentException;

/**
 * The cursors of the core's own activity sources (core#777): the last position read, encoded so a
 * caller cannot mistake it for an offset. A cursor that does not decode throws, and so never starts
 * a walk again from the first page.
 */
final class OpaqueCursor
{
    public static function encode(string $position): string
    {
        return rtrim(strtr(base64_encode($position), '+/', '-_'), '=');
    }

    /**
     * @throws InvalidArgumentException when the cursor is not one this source gave
     */
    public static function decode(string $cursor): string
    {
        $decoded = preg_match('/^[A-Za-z0-9_-]+$/', $cursor) === 1
            ? base64_decode(strtr($cursor, '-_', '+/'), true)
            : false;

        if ($decoded === false || $decoded === '' || self::encode($decoded) !== $cursor) {
            throw new InvalidArgumentException('The activity history cursor cannot be read.');
        }

        return $decoded;
    }
}
