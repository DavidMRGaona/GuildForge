<?php

declare(strict_types=1);

namespace App\Infrastructure\Authorization\Policies;

use App\Application\Authorization\Services\AuthorizationServiceInterface;

trait AuthorizesWithPermissions
{
    protected function authorize(object $user, string $permission): bool
    {
        return app(AuthorizationServiceInterface::class)->can($user, $permission);
    }

    /**
     * @param  array<string>  $permissions
     */
    protected function authorizeAny(object $user, array $permissions): bool
    {
        return app(AuthorizationServiceInterface::class)->canAny($user, $permissions);
    }

    /**
     * @param  array<string>  $permissions
     */
    protected function authorizeAll(object $user, array $permissions): bool
    {
        return app(AuthorizationServiceInterface::class)->canAll($user, $permissions);
    }
}
