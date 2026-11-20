<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\WebApp\Services\ServiceWorkerScriptInterface;
use App\Application\WebApp\Services\WebAppManifestInterface;
use App\Infrastructure\WebApp\ViteServiceWorkerScript;
use App\Infrastructure\WebApp\WebAppManifest;
use Illuminate\Support\ServiceProvider;

/**
 * The installable site (core#802): web app manifest and service worker, the ground of browser
 * push notifications.
 */
final class WebAppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(WebAppManifestInterface::class, WebAppManifest::class);
        $this->app->bind(ServiceWorkerScriptInterface::class, ViteServiceWorkerScript::class);
    }
}
