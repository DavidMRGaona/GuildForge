<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Discussions\Contracts\SubjectDiscussionInterface;
use App\Application\Publications\Services\PublicationSourceRegistryInterface;
use App\Infrastructure\Discussions\Services\NullSubjectDiscussion;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Publications\Observers\EventPublicationObserver;
use App\Infrastructure\Publications\Services\PublicationSourceRegistry;
use App\Infrastructure\Publications\Sources\EventPublicationSource;
use Illuminate\Support\ServiceProvider;

/**
 * Publication sources (events here, game tables and campaigns in their modules), the core's own
 * change events and the activity of their discussions elsewhere. Nothing in the core listens to
 * them nor offers discussions.
 */
final class PublicationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PublicationSourceRegistryInterface::class, PublicationSourceRegistry::class);
        // A forum module replaces it; without one, pages show no discussion activity
        $this->app->singletonIf(SubjectDiscussionInterface::class, NullSubjectDiscussion::class);
    }

    public function boot(): void
    {
        $this->app->make(PublicationSourceRegistryInterface::class)->register(EventPublicationSource::class);

        EventModel::observe(EventPublicationObserver::class);
    }
}
