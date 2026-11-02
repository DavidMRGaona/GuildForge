<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Authorization\Services\PermissionRegistryInterface;
use App\Application\Knowledge\Services\GuideConditionEvaluatorInterface;
use App\Application\Knowledge\Services\GuideRendererInterface;
use App\Application\Knowledge\Services\GuideVisibilityServiceInterface;
use App\Application\Knowledge\Services\KnowledgeCapabilityRegistryInterface;
use App\Application\Knowledge\Services\KnowledgeCatalogSynchronizerInterface;
use App\Application\Knowledge\Services\KnowledgeIndexerInterface;
use App\Application\Knowledge\Services\KnowledgeLocaleResolverInterface;
use App\Application\Knowledge\Services\KnowledgeMetricsServiceInterface;
use App\Application\Knowledge\Services\KnowledgeQueryServiceInterface;
use App\Application\Knowledge\Services\KnowledgeSearchInterface;
use App\Application\Modules\Services\EnabledModulesResolverInterface;
use App\Application\Modules\Services\ModuleManagerServiceInterface;
use App\Domain\Knowledge\Repositories\GuideRepositoryInterface;
use App\Domain\Modules\Events\ModuleDisabled;
use App\Domain\Modules\Events\ModuleEnabled;
use App\Domain\Modules\Events\ModuleUninstalled;
use App\Domain\Modules\Events\ModuleUpdated;
use App\Domain\Updates\Events\ModuleUpdateCompleted;
use App\Infrastructure\Knowledge\Capabilities\EmailVerificationRequiredCapability;
use App\Infrastructure\Knowledge\Catalog\KnowledgeCatalogSynchronizer;
use App\Infrastructure\Knowledge\Catalog\KnowledgeSourceLocator;
use App\Infrastructure\Knowledge\Lint\KnowledgeLinter;
use App\Infrastructure\Knowledge\Listeners\SyncKnowledgeOnModuleChange;
use App\Infrastructure\Knowledge\Parsing\GuideDocumentParser;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeGuideModel;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Repositories\EloquentGuideRepository;
use App\Infrastructure\Knowledge\Rendering\GuideMarkdownRenderer;
use App\Infrastructure\Knowledge\Search\DatabaseKnowledgeSearch;
use App\Infrastructure\Knowledge\Search\FallbackKnowledgeSearch;
use App\Infrastructure\Knowledge\Search\MeilisearchKnowledgeSearch;
use App\Infrastructure\Knowledge\Search\NullKnowledgeIndexer;
use App\Infrastructure\Knowledge\Search\ScoutKnowledgeIndexer;
use App\Infrastructure\Knowledge\Services\GuideConditionEvaluator;
use App\Infrastructure\Knowledge\Services\GuideVisibilityService;
use App\Infrastructure\Knowledge\Services\KnowledgeCapabilityRegistry;
use App\Infrastructure\Knowledge\Services\KnowledgeLocaleResolver;
use App\Infrastructure\Knowledge\Services\KnowledgeMetricsService;
use App\Infrastructure\Knowledge\Services\KnowledgeQueryService;
use GuzzleHttp\Client as HttpClient;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Meilisearch\Client as MeilisearchClient;
use Psr\Log\LoggerInterface;

class KnowledgeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(KnowledgeCapabilityRegistryInterface::class, KnowledgeCapabilityRegistry::class);
        $this->app->bind(GuideRepositoryInterface::class, EloquentGuideRepository::class);
        $this->app->bind(KnowledgeLocaleResolverInterface::class, KnowledgeLocaleResolver::class);
        $this->app->scoped(GuideConditionEvaluatorInterface::class, GuideConditionEvaluator::class);
        $this->app->scoped(GuideVisibilityServiceInterface::class, GuideVisibilityService::class);

        $this->app->bind(GuideRendererInterface::class, GuideMarkdownRenderer::class);
        $this->app->bind(KnowledgeQueryServiceInterface::class, KnowledgeQueryService::class);
        $this->app->bind(KnowledgeMetricsServiceInterface::class, KnowledgeMetricsService::class);

        $this->app->bind(KnowledgeSourceLocator::class, fn (Application $app): KnowledgeSourceLocator => new KnowledgeSourceLocator(
            $app->make(EnabledModulesResolverInterface::class),
            resource_path('knowledge'),
        ));
        $this->app->bind(KnowledgeCatalogSynchronizerInterface::class, KnowledgeCatalogSynchronizer::class);
        $this->app->bind(KnowledgeLinter::class, fn (Application $app, array $parameters): KnowledgeLinter => new KnowledgeLinter(
            is_string($parameters['corePath'] ?? null) ? $parameters['corePath'] : resource_path('knowledge'),
            $parameters['modules'] ?? $app->make(ModuleManagerServiceInterface::class),
            $app->make(GuideDocumentParser::class),
            $app->make(PermissionRegistryInterface::class),
            $app->make(KnowledgeCapabilityRegistryInterface::class),
        ));

        $this->registerSearch();
        $this->registerMeilisearchClient();
    }

    /**
     * Meilisearch when Scout is configured for it (falling back to the database search on any failure), otherwise the database search.
     */
    private function registerSearch(): void
    {
        $this->app->bind(KnowledgeSearchInterface::class, fn (Application $app): KnowledgeSearchInterface => $this->usesMeilisearch($app)
            ? new FallbackKnowledgeSearch(
                $app->make(MeilisearchKnowledgeSearch::class),
                $app->make(DatabaseKnowledgeSearch::class),
                $app->make(LoggerInterface::class),
            )
            : $app->make(DatabaseKnowledgeSearch::class));

        $this->app->bind(KnowledgeIndexerInterface::class, fn (Application $app): KnowledgeIndexerInterface => $this->usesMeilisearch($app)
            ? $app->make(ScoutKnowledgeIndexer::class)
            : $app->make(NullKnowledgeIndexer::class));
    }

    /**
     * Replaces Scout's client with one that gives up quickly, so a hung Meilisearch cannot hold a request or a deploy.
     */
    private function registerMeilisearchClient(): void
    {
        $this->app->singleton(MeilisearchClient::class, function (Application $app): MeilisearchClient {
            $config = $app->make('config');
            $host = $config->get('scout.meilisearch.host');
            $key = $config->get('scout.meilisearch.key');

            return new MeilisearchClient(
                is_string($host) ? $host : '',
                is_string($key) && $key !== '' ? $key : null,
                new HttpClient([
                    'timeout' => (float) $config->get('scout.meilisearch.timeout', 5),
                    'connect_timeout' => (float) $config->get('scout.meilisearch.connect_timeout', 2),
                ]),
            );
        });
    }

    private function usesMeilisearch(Application $app): bool
    {
        return $app->make('config')->get('scout.driver') === 'meilisearch';
    }

    public function boot(): void
    {
        // Only the indexer writes to the search index: saving a guide never pushes it on its own
        KnowledgeGuideModel::disableSearchSyncing();

        $this->app->make(KnowledgeCapabilityRegistryInterface::class)
            ->register(EmailVerificationRequiredCapability::class);

        foreach ([ModuleEnabled::class, ModuleDisabled::class, ModuleUpdated::class, ModuleUninstalled::class, ModuleUpdateCompleted::class] as $event) {
            Event::listen($event, SyncKnowledgeOnModuleChange::class);
        }
    }
}
