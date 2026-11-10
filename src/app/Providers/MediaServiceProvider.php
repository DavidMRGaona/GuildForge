<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Media\Services\MediaSourceRegistryInterface;
use App\Infrastructure\Media\Services\MediaSourceRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * Panel media library: the sources that say which records use each stored file.
 */
final class MediaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MediaSourceRegistryInterface::class, MediaSourceRegistry::class);
    }
}
