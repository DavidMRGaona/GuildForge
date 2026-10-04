<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Application\Authorization\Services\AuthorizationServiceInterface;

/**
 * Permission checks for Filament pages, which (unlike resources) have no policy.
 */
trait ChecksPermissions
{
    protected static function userCan(string $permission): bool
    {
        $user = auth()->user();

        return $user !== null && app(AuthorizationServiceInterface::class)->can($user, $permission);
    }
}
