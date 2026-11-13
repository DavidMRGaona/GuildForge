<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Realtime\Services\RealtimeChannelRegistryInterface;
use App\Application\Realtime\Services\RealtimeConfigurationInterface;
use App\Application\Realtime\Services\RealtimePublisherInterface;
use App\Application\Realtime\Services\RealtimeSubscriptionAuthorizerInterface;
use App\Infrastructure\Realtime\BroadcastRealtimePublisher;
use App\Infrastructure\Realtime\RealtimeChannelRegistry;
use App\Infrastructure\Realtime\ReverbRealtimeConfiguration;
use App\Infrastructure\Realtime\ReverbSubscriptionAuthorizer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

/**
 * Real-time broadcasting for the core and the modules (core#493), optional per installation:
 * Laravel Reverb when configured; without it everything keeps working with periodic requests.
 */
final class RealtimeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(RealtimeConfigurationInterface::class, ReverbRealtimeConfiguration::class);
        $this->app->singleton(RealtimeChannelRegistryInterface::class, RealtimeChannelRegistry::class);
        $this->app->bind(RealtimePublisherInterface::class, BroadcastRealtimePublisher::class);
        $this->app->bind(RealtimeSubscriptionAuthorizerInterface::class, ReverbSubscriptionAuthorizer::class);
    }

    public function boot(RealtimeConfigurationInterface $configuration): void
    {
        $this->switchOffWhenIncomplete($configuration);

        require base_path('routes/channels.php');
    }

    /**
     * `reverb` with a missing or invalid setting would make every broadcast fail: the connection
     * falls back to `null`, so nothing tries. Reported where someone reads it once (a worker, the
     * Reverb server, a deploy command), never on every web request.
     */
    private function switchOffWhenIncomplete(RealtimeConfigurationInterface $configuration): void
    {
        if (! $configuration->isIncomplete()) {
            return;
        }

        config(['broadcasting.default' => 'null']);

        if ($this->app->runningInConsole()) {
            Log::warning('BROADCAST_CONNECTION is reverb but a REVERB_* setting is missing or invalid; real time is off.');
        }
    }
}
