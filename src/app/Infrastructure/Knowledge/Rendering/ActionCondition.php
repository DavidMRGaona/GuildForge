<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Rendering;

use App\Application\Authorization\Services\PermissionRegistryInterface;

/**
 * The `if=` condition of an action button: a permission or a capability key, optionally negated with `!`.
 * Shared by the renderer (which evaluates it) and the linter (which validates it).
 */
final class ActionCondition
{
    /**
     * @return array{negated: bool, key: string}
     */
    public static function parse(string $condition): array
    {
        $negated = str_starts_with($condition, '!');

        return ['negated' => $negated, 'key' => $negated ? substr($condition, 1) : $condition];
    }

    /**
     * @return 'permission'|'capability'
     */
    public static function kind(string $key, PermissionRegistryInterface $permissions): string
    {
        return $permissions->has($key) ? 'permission' : 'capability';
    }
}
