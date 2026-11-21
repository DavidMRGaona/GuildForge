<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Activity\Contracts\ActivitySourceInterface;
use App\Application\Activity\Services\ActivityPublisherInterface;
use App\Application\Activity\Services\ActivityReaderInterface;
use App\Application\Activity\Services\ActivitySourceRegistryInterface;
use App\Infrastructure\Activity\ActivityReader;
use App\Infrastructure\Activity\ActivitySourceRegistry;
use App\Infrastructure\Activity\EventActivityPublisher;
use App\Infrastructure\Activity\Observers\ProposalActivityObserver;
use App\Infrastructure\Activity\Observers\UserActivityObserver;
use App\Infrastructure\Activity\Observers\UserProfileActivityObserver;
use App\Infrastructure\Activity\Sources\AccountActivitySource;
use App\Infrastructure\Activity\Sources\ProfileCompletedActivitySource;
use App\Infrastructure\Activity\Sources\ProposalActivitySource;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileModel;
use Illuminate\Support\ServiceProvider;

/**
 * The activity contract (core#774): modules report who took part in their things, consumers read it.
 * The registry is a singleton the modules fill while they boot; the reader is scoped, so the sources
 * it builds and the warnings it remembers in memory last one request or job (the cache keeps
 * warnings apart for a day).
 *
 * The core reports three things of its own (core#777), under `core.`: account age, complete profile
 * and approved proposals. Its observers publish them whatever path changes the data.
 */
final class ActivityServiceProvider extends ServiceProvider
{
    /** @var list<class-string<ActivitySourceInterface>> */
    public const array CORE_SOURCES = [
        AccountActivitySource::class,
        ProfileCompletedActivitySource::class,
        ProposalActivitySource::class,
    ];

    public function register(): void
    {
        // Built by hand so the cache is only asked for when warning, not at boot. The core's sources
        // come first, the modules add theirs as they load.
        $this->app->singleton(ActivitySourceRegistryInterface::class, static function ($app): ActivitySourceRegistry {
            $registry = new ActivitySourceRegistry($app);
            $registry->registerMany(self::CORE_SOURCES);

            return $registry;
        });
        $this->app->scoped(ActivityReaderInterface::class, ActivityReader::class);
        // Scoped: what an open transaction already announces is known to every caller of the request or job
        $this->app->scoped(ActivityPublisherInterface::class, EventActivityPublisher::class);
    }

    public function boot(): void
    {
        UserModel::observe(UserActivityObserver::class);
        UserProfileModel::observe(UserProfileActivityObserver::class);
        EventModel::observe(ProposalActivityObserver::class);
        ArticleModel::observe(ProposalActivityObserver::class);
    }
}
