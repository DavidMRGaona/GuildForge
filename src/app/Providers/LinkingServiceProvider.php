<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Linking\Services\LinkableRecordLookupInterface;
use App\Application\Linking\Services\LinkableRecordSourceRegistryInterface;
use App\Filament\Linking\Sources\EventLinkableRecordSource;
use App\Infrastructure\Linking\Services\LinkableRecordLookup;
use App\Infrastructure\Linking\Services\LinkableRecordSourceRegistry;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/**
 * Linkable records: sources offering a part of the site's records (events here, tournaments,
 * bookings… in their modules) so other modules can link their data to them. Nothing in the core
 * consumes them.
 */
final class LinkingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The core's source goes in when the registry is built, before any module's, so the first
        // source registered for a type (the one that wins) is always the core's for its own types
        $this->app->singleton(LinkableRecordSourceRegistryInterface::class, function (Application $app): LinkableRecordSourceRegistry {
            $registry = new LinkableRecordSourceRegistry($app);
            $registry->register(EventLinkableRecordSource::class);

            return $registry;
        });

        $this->app->singleton(LinkableRecordLookupInterface::class, LinkableRecordLookup::class);
    }
}
