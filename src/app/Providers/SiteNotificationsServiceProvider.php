<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\SiteNotifications\Contracts\HasSiteNotification;
use App\Application\SiteNotifications\Services\SiteNotificationPreferenceServiceInterface;
use App\Application\SiteNotifications\Services\SiteNotificationServiceInterface;
use App\Domain\SiteNotifications\Repositories\SiteNotificationPreferenceRepositoryInterface;
use App\Domain\SiteNotifications\Repositories\SiteNotificationRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentSiteNotificationPreferenceRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentSiteNotificationRepository;
use App\Infrastructure\SiteNotifications\SiteNotificationChannel;
use App\Infrastructure\SiteNotifications\SiteNotificationPreferenceService;
use App\Infrastructure\SiteNotifications\SiteNotificationService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\ServiceProvider;

/**
 * The notification centre of the site (core#491): the «site» channel, each account's notices and
 * the site switch of each mail category.
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
    }

    public function boot(): void
    {
        $this->callAfterResolving(ChannelManager::class, static function (ChannelManager $channels, Application $app): void {
            $channels->extend(HasSiteNotification::CHANNEL, fn (): SiteNotificationChannel => $app->make(SiteNotificationChannel::class));
        });
    }
}
