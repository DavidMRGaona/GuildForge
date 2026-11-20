<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\WebPush\Services\WebPushConfigurationInterface;
use App\Application\WebPush\Services\WebPushNotifierInterface;
use App\Application\WebPush\Services\WebPushPreferenceInterface;
use App\Application\WebPush\Services\WebPushSenderInterface;
use App\Application\WebPush\Services\WebPushSubscriptionServiceInterface;
use App\Domain\Events\UserAnonymized;
use App\Domain\Events\UserDeactivated;
use App\Infrastructure\WebPush\ConfiguredWebPush;
use App\Infrastructure\WebPush\EloquentWebPushSubscriptionService;
use App\Infrastructure\WebPush\Listeners\ForgetAccountWebPushSubscriptions;
use App\Infrastructure\WebPush\Listeners\ForgetDeviceWebPushSubscriptionOnLogout;
use App\Infrastructure\WebPush\MinishlinkWebPushSender;
use App\Infrastructure\WebPush\NoWebPushPreferences;
use App\Infrastructure\WebPush\WebPushNotifier;
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
        $this->app->bind(WebPushPreferenceInterface::class, NoWebPushPreferences::class);
        $this->app->bind(WebPushNotifierInterface::class, WebPushNotifier::class);
        $this->app->bind(WebPushSenderInterface::class, MinishlinkWebPushSender::class);
    }

    public function boot(WebPushConfigurationInterface $configuration): void
    {
        Event::listen([UserDeactivated::class, UserAnonymized::class], ForgetAccountWebPushSubscriptions::class);
        Event::listen(Logout::class, ForgetDeviceWebPushSubscriptionOnLogout::class);

        // Reported where someone reads it once (a worker, a deploy command), never on every request
        if ($this->app->runningInConsole() && $configuration->isIncomplete()) {
            Log::warning('A WEB_PUSH_* setting is missing or invalid; browser push notifications are off.');
        }
    }
}
