<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\WebPush\Services\WebPushConfigurationInterface;
use App\Application\WebPush\Services\WebPushNotifierInterface;
use App\Application\WebPush\Services\WebPushPreferenceInterface;
use App\Application\WebPush\Services\WebPushPreferenceServiceInterface;
use App\Application\WebPush\Services\WebPushSenderInterface;
use App\Application\WebPush\Services\WebPushSubscriptionServiceInterface;
use App\Domain\Events\UserAnonymized;
use App\Domain\Events\UserDeactivated;
use App\Domain\WebPush\Repositories\WebPushPreferenceRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentWebPushPreferenceRepository;
use App\Infrastructure\WebPush\ConfiguredWebPush;
use App\Infrastructure\WebPush\EloquentWebPushSubscriptionService;
use App\Infrastructure\WebPush\Listeners\ForgetAccountWebPushPreferences;
use App\Infrastructure\WebPush\Listeners\ForgetAccountWebPushSubscriptions;
use App\Infrastructure\WebPush\Listeners\ForgetDeviceWebPushSubscriptionOnLogout;
use App\Infrastructure\WebPush\MinishlinkWebPushSender;
use App\Infrastructure\WebPush\WebPushNotifier;
use App\Infrastructure\WebPush\WebPushPreferenceService;
use Illuminate\Auth\Events\CurrentDeviceLogout;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

/**
 * Browser push notifications (core#803), optional per installation: the notices of the bell also
 * reach the browsers people turned them on in, through the standard Web Push protocol. Without
 * the VAPID keys nothing shows and nothing is sent.
 */
final class WebPushServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(WebPushConfigurationInterface::class, ConfiguredWebPush::class);
        $this->app->bind(WebPushSubscriptionServiceInterface::class, EloquentWebPushSubscriptionService::class);
        // Push choices per category (core#804), off until the person turns them on
        $this->app->bind(WebPushPreferenceRepositoryInterface::class, EloquentWebPushPreferenceRepository::class);
        $this->app->bind(WebPushPreferenceInterface::class, WebPushPreferenceService::class);
        $this->app->bind(WebPushPreferenceServiceInterface::class, WebPushPreferenceService::class);
        $this->app->bind(WebPushNotifierInterface::class, WebPushNotifier::class);
        $this->app->bind(WebPushSenderInterface::class, MinishlinkWebPushSender::class);
    }

    public function boot(WebPushConfigurationInterface $configuration): void
    {
        Event::listen([UserDeactivated::class, UserAnonymized::class], ForgetAccountWebPushSubscriptions::class);
        Event::listen(UserAnonymized::class, ForgetAccountWebPushPreferences::class);
        // Signing out everywhere or on this device alone (CurrentDeviceLogout, core#779)
        Event::listen([Logout::class, CurrentDeviceLogout::class], ForgetDeviceWebPushSubscriptionOnLogout::class);

        // Reported by console processes only (each worker start, each command, so each schedule:run),
        // never on a web request
        if ($this->app->runningInConsole() && $configuration->isIncomplete()) {
            Log::warning('A WEB_PUSH_* setting is missing or invalid; browser push notifications are off.');
        }
    }
}
