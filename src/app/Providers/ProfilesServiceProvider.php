<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Profiles\Services\ProfileBadgesInterface;
use App\Application\Profiles\Services\ProfileBadgeSourceRegistryInterface;
use App\Application\Profiles\Services\ProfileCardServiceInterface;
use App\Application\Profiles\Services\ProfileEditorInterface;
use App\Application\Profiles\Services\ProfileLayoutOptionsInterface;
use App\Application\Profiles\Services\ProfileMembershipDisplayInterface;
use App\Application\Profiles\Services\ProfileRendererInterface;
use App\Application\Profiles\Services\ProfileSectionRegistryInterface;
use App\Application\Profiles\Services\ProfileStatRegistryInterface;
use App\Application\Profiles\Services\ProfileSummaryRendererInterface;
use App\Application\Profiles\Services\PublicProfileServiceInterface;
use App\Application\Profiles\Services\UserProfileServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileModel;
use App\Infrastructure\Profiles\Observers\AccountMembershipDisplayObserver;
use App\Infrastructure\Profiles\Observers\ProfileMembershipDisplayObserver;
use App\Infrastructure\Profiles\Observers\UserProfileObserver;
use App\Infrastructure\Profiles\ProfileBadgeCollector;
use App\Infrastructure\Profiles\ProfileBadgeSourceRegistry;
use App\Infrastructure\Profiles\ProfileEditor;
use App\Infrastructure\Profiles\ProfileLayoutOptions;
use App\Infrastructure\Profiles\ProfileRenderer;
use App\Infrastructure\Profiles\ProfileSectionRegistry;
use App\Infrastructure\Profiles\ProfileStatRegistry;
use App\Infrastructure\Profiles\Sections\AboutProfileSection;
use App\Infrastructure\Profiles\Services\ProfileBadges;
use App\Infrastructure\Profiles\Services\ProfileCardService;
use App\Infrastructure\Profiles\Services\ProfileMembershipDisplay;
use App\Infrastructure\Profiles\Services\ProfileMembershipDisplayAnnouncer;
use App\Infrastructure\Profiles\Services\PublicProfileService;
use App\Infrastructure\Profiles\Services\UserProfileService;
use App\Infrastructure\Profiles\StoredProfiles;
use Illuminate\Contracts\Cache\Repository as Cache;
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
        // Featured badges (core#776): sources kept as classes, built and asked once per request or job
        $this->app->singleton(ProfileBadgeSourceRegistryInterface::class, static fn ($app): ProfileBadgeSourceRegistry => new ProfileBadgeSourceRegistry(
            static fn (): Cache => $app->make(Cache::class),
        ));
        $this->app->scoped(ProfileBadgeCollector::class);
        $this->app->bind(ProfileBadgesInterface::class, ProfileBadges::class);
        $this->app->scoped(StoredProfiles::class);
        // One renderer per request or job draws both the page and the card (core#744), so what it
        // built and read is shared; each interface asks for it, so replacing one leaves the other
        $this->app->scoped(ProfileRenderer::class);
        $this->app->scoped(ProfileRendererInterface::class, static fn ($app): ProfileRenderer => $app->make(ProfileRenderer::class));
        $this->app->scoped(ProfileSummaryRendererInterface::class, static fn ($app): ProfileRenderer => $app->make(ProfileRenderer::class));
        $this->app->bind(PublicProfileServiceInterface::class, PublicProfileService::class);
        $this->app->bind(ProfileCardServiceInterface::class, ProfileCardService::class);
        // The owner's editor (core#743): what it offers is read once per request, like the renderer
        $this->app->scoped(ProfileLayoutOptionsInterface::class, ProfileLayoutOptions::class);
        $this->app->bind(ProfileEditorInterface::class, ProfileEditor::class);
        // Whether an account shows its membership (core#824) and the announcer of the changes that can alter it
        $this->app->bind(ProfileMembershipDisplayInterface::class, ProfileMembershipDisplay::class);
        $this->app->scoped(ProfileMembershipDisplayAnnouncer::class);
    }

    public function boot(): void
    {
        UserModel::observe(UserProfileObserver::class);
        // What can change whether an account shows its membership (core#824)
        UserModel::observe(AccountMembershipDisplayObserver::class);
        UserProfileModel::observe(ProfileMembershipDisplayObserver::class);
    }
}
