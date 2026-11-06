<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Search;

use App\Application\Search\DTOs\SiteSearchHitDTO;
use App\Application\Search\DTOs\SiteSearchQueryDTO;
use App\Application\Search\DTOs\SiteSearchResultDTO;
use App\Application\Search\Services\SiteSearchInterface;
use App\Domain\Search\Enums\SearchAudience;
use App\Infrastructure\Search\Persistence\Eloquent\Models\SearchDocumentModel;
use App\Infrastructure\Search\Search\DatabaseSiteSearch;
use App\Infrastructure\Search\Search\FallbackSiteSearch;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Support\Knowledge\RecordingLogger;
use Tests\TestCase;

final class FallbackSiteSearchTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_uses_the_primary_when_it_works(): void
    {
        $logger = new RecordingLogger;
        $search = new FallbackSiteSearch($this->returning('Primaria'), $this->returning('Respaldo'), $logger);

        $result = $search->search($this->siteQuery('algo'));

        $this->assertSame('Primaria', $result->hits[0]->title);
        $this->assertSame([], $logger->records);
    }

    public function test_falls_back_to_database_and_logs_on_engine_error(): void
    {
        $logger = new RecordingLogger;
        $search = new FallbackSiteSearch($this->throwing(), $this->returning('Respaldo'), $logger);

        $result = $search->search($this->siteQuery('algo'));

        $this->assertSame('Respaldo', $result->hits[0]->title);
        $this->assertCount(1, $logger->records);
        $this->assertSame('warning', $logger->records[0]['level']);
        $this->assertStringContainsString('Connection refused', $logger->records[0]['message']);
    }

    public function test_the_binding_follows_the_scout_driver(): void
    {
        config(['scout.driver' => 'null']);
        $this->assertSame(DatabaseSiteSearch::class, $this->resolvedClass(SiteSearchInterface::class));

        config(['scout.driver' => 'meilisearch']);
        $this->assertSame(FallbackSiteSearch::class, $this->resolvedClass(SiteSearchInterface::class));
    }

    public function test_an_unreachable_meilisearch_falls_back_to_the_database(): void
    {
        config(['scout.driver' => 'meilisearch', 'scout.meilisearch.host' => 'http://127.0.0.1:1', 'scout.meilisearch.key' => 'none']);
        SearchDocumentModel::query()->create([
            'type' => 'events',
            'source_id' => (string) Str::uuid(),
            'title' => 'Torneo de dragones',
            'body' => '',
            'url' => '/eventos/torneo',
            'audience' => SearchAudience::Public->value,
            'locale' => 'es',
        ]);

        $log = Log::spy();

        $result = $this->app->make(SiteSearchInterface::class)->search($this->siteQuery('dragones'));

        $this->assertSame(['Torneo de dragones'], array_map(static fn (SiteSearchHitDTO $hit): string => $hit->title, $result->hits));
        $this->assertSame(['events' => 1], $result->counts);
        // The fallback is logged, so an engine that is down is noticed
        $log->shouldHaveReceived('warning')->once();
    }

    private function resolvedClass(string $abstract): string
    {
        $instance = $this->app->make($abstract);
        $this->assertIsObject($instance);

        return $instance::class;
    }

    private function siteQuery(string $query): SiteSearchQueryDTO
    {
        return new SiteSearchQueryDTO(
            query: $query,
            audiences: [SearchAudience::Public],
            types: ['events'],
            type: null,
            offset: 0,
            limit: 20,
            user: null,
            includeHelp: false,
        );
    }

    private function returning(string $title): SiteSearchInterface
    {
        return new readonly class($title) implements SiteSearchInterface
        {
            public function __construct(private string $title) {}

            public function search(SiteSearchQueryDTO $query): SiteSearchResultDTO
            {
                return new SiteSearchResultDTO(
                    [new SiteSearchHitDTO('events', $this->title, '/e', null, null, null, null, false, [])],
                    ['events' => 1],
                    1,
                );
            }
        };
    }

    private function throwing(): SiteSearchInterface
    {
        return new class implements SiteSearchInterface
        {
            public function search(SiteSearchQueryDTO $query): SiteSearchResultDTO
            {
                throw new RuntimeException('Connection refused');
            }
        };
    }
}
