<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Knowledge\Services\KnowledgeCatalogSynchronizerInterface;
use App\Application\Modules\Services\EnabledModulesResolverInterface;
use App\Domain\Knowledge\Repositories\GuideRepositoryInterface;
use App\Infrastructure\Knowledge\Catalog\KnowledgeCatalogSynchronizer;
use App\Infrastructure\Knowledge\Catalog\KnowledgeSourceLocator;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Repositories\EloquentGuideRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class KnowledgeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(GuideRepositoryInterface::class, EloquentGuideRepository::class);

        $this->app->bind(KnowledgeSourceLocator::class, fn (Application $app): KnowledgeSourceLocator => new KnowledgeSourceLocator(
            $app->make(EnabledModulesResolverInterface::class),
            resource_path('knowledge'),
        ));
        $this->app->bind(KnowledgeCatalogSynchronizerInterface::class, KnowledgeCatalogSynchronizer::class);
    }
}
