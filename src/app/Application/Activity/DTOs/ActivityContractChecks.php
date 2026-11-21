<?php

declare(strict_types=1);

namespace App\Application\Activity\DTOs;

/**
 * @internal What the activity DTOs and the reader check at run time (core#774): a module may pass
 * anything, whatever its PHPDoc says, so the shapes are checked on values read as mixed.
 */
final class ActivityContractChecks
{
    /**
     * @param  class-string  $class
     */
    public static function isListOf(mixed $items, string $class): bool
    {
        return is_array($items) && array_is_list($items) && self::areAll($items, $class);
    }

    /**
     * @param  array<mixed>  $items
     * @param  class-string  $class
     */
    public static function areAll(array $items, string $class): bool
    {
        return array_all($items, fn ($item) => $item instanceof $class);
    }
}
