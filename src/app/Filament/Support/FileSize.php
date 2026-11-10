<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Illuminate\Support\Number;

/**
 * File sizes as people read them, in the app locale («1,5 MB»), with binary multiples.
 */
final class FileSize
{
    private const array UNITS = ['B', 'KB', 'MB', 'GB', 'TB'];

    public static function format(int $bytes): string
    {
        $value = (float) max(0, $bytes);
        $unit = 0;

        while ($value >= 1024 && $unit < count(self::UNITS) - 1) {
            $value /= 1024;
            $unit++;
        }

        $number = Number::format($value, maxPrecision: $unit === 0 ? 0 : 1, locale: app()->getLocale());

        return ($number === false ? (string) round($value, 1) : $number).' '.self::UNITS[$unit];
    }
}
