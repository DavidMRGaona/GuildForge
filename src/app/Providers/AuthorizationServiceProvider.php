<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Authorization\Services\PermissionRegistryInterface;
use App\Application\Authorization\Services\RoleServiceInterface;
use App\Domain\Authorization\Repositories\PermissionRepositoryInterface;
use App\Domain\Authorization\Repositories\RoleRepositoryInterface;
use App\Infrastructure\Authorization\Repositories\EloquentPermissionRepository;
use App\Infrastructure\Authorization\Repositories\EloquentRoleRepository;
use App\Infrastructure\Authorization\Services\AuthorizationService;
use App\Infrastructure\Authorization\Services\PermissionRegistry;
use App\Infrastructure\Authorization\Services\RoleService;
use Illuminate\Support\ServiceProvider;

class AuthorizationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Repository bindings
        $this->app->bind(RoleRepositoryInterface::class, EloquentRoleRepository::class);
        $this->app->bind(PermissionRepositoryInterface::class, EloquentPermissionRepository::class);

        // Service bindings
        $this->app->singleton(AuthorizationServiceInterface::class, AuthorizationService::class);
        $this->app->singleton(RoleServiceInterface::class, RoleService::class);
        $this->app->singleton(PermissionRegistryInterface::class, PermissionRegistry::class);
    }

    public function boot(): void
    {
        // Register core permissions in memory (for use in policies and code)
        // The actual database sync is done via `php artisan permissions:sync`
        $this->app->booted(function () {
            /** @var PermissionRegistryInterface $registry */
            $registry = $this->app->make(PermissionRegistryInterface::class);
            $registry->registerMany(\App\Infrastructure\Authorization\CorePermissionDefinitions::all());
        });
    }
}
