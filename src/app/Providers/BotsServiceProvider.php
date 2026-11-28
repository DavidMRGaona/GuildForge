<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Bots\Services\BotRegistryInterface;
use App\Infrastructure\Bots\BotRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * Bot accounts (core#863). The registry is a singleton the modules fill while they boot; the core
 * registers no bots of its own.
 */
final class BotsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Built by hand so the cache is only asked for when warning, not at boot
        $this->app->singleton(BotRegistryInterface::class, static fn ($app): BotRegistry => new BotRegistry($app));
    }
}
