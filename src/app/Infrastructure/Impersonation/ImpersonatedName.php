<?php

declare(strict_types=1);

namespace App\Infrastructure\Impersonation;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The name every impersonation text shows for the account being viewed (core#600): its display
 * name, or the account name when it has none, so the bands, the top bar reminder, the panel
 * notices and the site notice all call the person the same way.
 */
final class ImpersonatedName
{
    public static function of(?Authenticatable $account): string
    {
        if (! $account instanceof Authenticatable) {
            return '';
        }

        $displayName = $account->display_name ?? null;

        if (is_string($displayName) && trim($displayName) !== '') {
            return $displayName;
        }

        $name = $account->name ?? null;

        return is_string($name) ? $name : '';
    }
}
