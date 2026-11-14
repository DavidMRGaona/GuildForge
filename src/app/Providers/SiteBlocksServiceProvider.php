<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\SiteBlocks\Services\SiteBlockRegistryInterface;
use App\Application\SiteBlocks\Services\SiteBlockRendererInterface;
use App\Infrastructure\SiteBlocks\SiteBlockRegistry;
use App\Infrastructure\SiteBlocks\SiteBlockRenderer;
use Illuminate\Support\ServiceProvider;

/**
 * Blocks the administration places on the public site, in the footer and inside popups (core#696).
 * The registry is a singleton the modules fill while they boot; the renderer is scoped, so what it
 * remembers about warnings lasts one request or job (the cache keeps them apart for a day).
 */
final class SiteBlocksServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SiteBlockRegistryInterface::class, SiteBlockRegistry::class);
        $this->app->scoped(SiteBlockRendererInterface::class, SiteBlockRenderer::class);
    }
}
