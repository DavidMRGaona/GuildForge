<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Profiles\Services\UserProfileServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Profiles\Observers\UserProfileObserver;
use App\Infrastructure\Profiles\Services\UserProfileService;
use Illuminate\Support\ServiceProvider;

/**
 * Public profiles (core#739): one row per account, created and kept in step by an observer.
 */
final class ProfilesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserProfileServiceInterface::class, UserProfileService::class);
    }

    public function boot(): void
    {
        UserModel::observe(UserProfileObserver::class);
    }
}
