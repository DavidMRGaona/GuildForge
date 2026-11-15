<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Profiles\Services\ProfileRendererInterface;
use App\Application\Profiles\Services\ProfileSectionRegistryInterface;
use App\Application\Profiles\Services\ProfileStatRegistryInterface;
use App\Application\Profiles\Services\PublicProfileServiceInterface;
use App\Application\Profiles\Services\UserProfileServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Profiles\Observers\UserProfileObserver;
use App\Infrastructure\Profiles\ProfileRenderer;
use App\Infrastructure\Profiles\ProfileSectionRegistry;
use App\Infrastructure\Profiles\ProfileStatRegistry;
use App\Infrastructure\Profiles\Sections\AboutProfileSection;
use App\Infrastructure\Profiles\Services\PublicProfileService;
use App\Infrastructure\Profiles\Services\UserProfileService;
use App\Infrastructure\Profiles\StoredProfiles;
use Illuminate\Support\ServiceProvider;

/**
 * Public profiles (core#739): one row per account, created and kept in step by an observer, and the
 * sections and stats the core and the modules draw on it (core#742). The registries are singletons
 * the modules fill while they boot; the renderer and the rows it reads are scoped, so what they
 * remember lasts one request or job (the cache keeps warnings apart for a day).
 */
final class ProfilesServiceProvider extends ServiceProvider
{
    /** The core's own sections */
    public const array CORE_SECTIONS = [
        AboutProfileSection::class,
    ];

    public function register(): void
    {
        $this->app->bind(UserProfileServiceInterface::class, UserProfileService::class);

        // Built by hand so the cache is only asked for when warning, not at boot
        $this->app->singleton(ProfileSectionRegistryInterface::class, static function ($app): ProfileSectionRegistry {
            $registry = new ProfileSectionRegistry($app);
            $registry->registerMany(self::CORE_SECTIONS);

            return $registry;
        });
        $this->app->singleton(ProfileStatRegistryInterface::class, static fn ($app): ProfileStatRegistry => new ProfileStatRegistry($app));
        $this->app->scoped(StoredProfiles::class);
        $this->app->scoped(ProfileRendererInterface::class, ProfileRenderer::class);
        $this->app->bind(PublicProfileServiceInterface::class, PublicProfileService::class);
    }

    public function boot(): void
    {
        UserModel::observe(UserProfileObserver::class);
    }
}
