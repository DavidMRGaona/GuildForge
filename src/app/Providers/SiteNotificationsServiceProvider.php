<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\SiteNews\Services\SiteNewsReaderInterface;
use App\Application\SiteNotifications\Contracts\HasSiteNotification;
use App\Application\SiteNotifications\Services\SiteNotificationPreferenceServiceInterface;
use App\Application\SiteNotifications\Services\SiteNotificationServiceInterface;
use App\Domain\SiteNotifications\Repositories\SiteNotificationPreferenceRepositoryInterface;
use App\Domain\SiteNotifications\Repositories\SiteNotificationRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\GalleryModel;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentSiteNotificationPreferenceRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentSiteNotificationRepository;
use App\Infrastructure\SiteNews\EloquentSiteNewsReader;
use App\Infrastructure\SiteNews\SiteNewsObserver;
use App\Infrastructure\SiteNotifications\SiteNotificationChannel;
use App\Infrastructure\SiteNotifications\SiteNotificationPreferenceService;
use App\Infrastructure\SiteNotifications\SiteNotificationService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\ServiceProvider;

/**
 * The notification centre of the site (core#491): the «site» channel, each account's notices and
 * the site switch of each mail category. Also the core's own news: the first publication of an
 * event, an article or a gallery in everybody's bell (core#613).
 */
final class SiteNotificationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SiteNotificationRepositoryInterface::class, EloquentSiteNotificationRepository::class);
        $this->app->bind(SiteNotificationPreferenceRepositoryInterface::class, EloquentSiteNotificationPreferenceRepository::class);
        $this->app->bind(SiteNotificationServiceInterface::class, SiteNotificationService::class);
        $this->app->bind(SiteNotificationPreferenceServiceInterface::class, SiteNotificationPreferenceService::class);
        $this->app->scoped(SiteNotificationChannel::class);
        $this->app->bind(SiteNewsReaderInterface::class, EloquentSiteNewsReader::class);
    }

    public function boot(): void
    {
        $this->callAfterResolving(ChannelManager::class, static function (ChannelManager $channels, Application $app): void {
            $channels->extend(HasSiteNotification::CHANNEL, fn (): SiteNotificationChannel => $app->make(SiteNotificationChannel::class));
        });

        EventModel::observe(SiteNewsObserver::class);
        ArticleModel::observe(SiteNewsObserver::class);
        GalleryModel::observe(SiteNewsObserver::class);
    }
}
