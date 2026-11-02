<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Knowledge;

use App\Application\Knowledge\DTOs\Response\KnowledgeSearchHitDTO;
use App\Application\Knowledge\Services\KnowledgeSearchInterface;
use App\Infrastructure\Knowledge\Search\DatabaseKnowledgeSearch;
use App\Infrastructure\Knowledge\Search\FallbackKnowledgeSearch;
use RuntimeException;
use Tests\Support\Knowledge\KnowledgeGuides;
use Tests\Support\Knowledge\RecordingLogger;
use Tests\TestCase;

final class FallbackKnowledgeSearchTest extends TestCase
{
    private function resolvedClass(string $abstract): string
    {
        $instance = $this->app->make($abstract);
        $this->assertIsObject($instance);

        return $instance::class;
    }

    private function hit(string $id): KnowledgeSearchHitDTO
    {
        return new KnowledgeSearchHitDTO($id, 'Título', 'getting-started', []);
    }

    /**
     * @param  list<KnowledgeSearchHitDTO>  $hits
     */
    private function returning(array $hits): KnowledgeSearchInterface
    {
        return new readonly class($hits) implements KnowledgeSearchInterface
        {
            /**
             * @param  list<KnowledgeSearchHitDTO>  $hits
             */
            public function __construct(private array $hits) {}

            public function search(string $query, array $candidates, int $limit): array
            {
                return $this->hits;
            }
        };
    }

    private function throwing(): KnowledgeSearchInterface
    {
        return new class implements KnowledgeSearchInterface
        {
            public function search(string $query, array $candidates, int $limit): array
            {
                throw new RuntimeException('Connection refused');
            }
        };
    }

    public function test_it_uses_the_primary_when_it_works(): void
    {
        $logger = new RecordingLogger;
        $search = new FallbackKnowledgeSearch($this->returning([$this->hit('core.primary')]), $this->returning([$this->hit('core.fallback')]), $logger);

        $hits = $search->search('algo', [KnowledgeGuides::make('core.primary')], 10);

        $this->assertSame('core.primary', $hits[0]->id);
        $this->assertSame([], $logger->records);
    }

    public function test_it_uses_the_fallback_when_primary_throws(): void
    {
        $logger = new RecordingLogger;
        $search = new FallbackKnowledgeSearch($this->throwing(), $this->returning([$this->hit('core.fallback')]), $logger);

        $hits = $search->search('algo', [KnowledgeGuides::make('core.fallback')], 10);

        $this->assertSame('core.fallback', $hits[0]->id);
        $this->assertCount(1, $logger->records);
        $this->assertSame('warning', $logger->records[0]['level']);
        $this->assertStringContainsString('Connection refused', $logger->records[0]['message']);
    }

    public function test_the_binding_follows_the_scout_driver(): void
    {
        config(['scout.driver' => 'null']);
        $this->assertSame(DatabaseKnowledgeSearch::class, $this->resolvedClass(KnowledgeSearchInterface::class));

        config(['scout.driver' => 'meilisearch']);
        $this->assertSame(FallbackKnowledgeSearch::class, $this->resolvedClass(KnowledgeSearchInterface::class));
    }

    public function test_an_unreachable_meilisearch_falls_back_to_the_database(): void
    {
        config(['scout.driver' => 'meilisearch', 'scout.meilisearch.host' => 'http://127.0.0.1:1', 'scout.meilisearch.key' => 'none']);

        $hits = $this->app->make(KnowledgeSearchInterface::class)
            ->search('contraseña', [KnowledgeGuides::make('core.password', title: 'Cambiar la contraseña')], 10);

        $this->assertSame(['core.password'], array_map(static fn (KnowledgeSearchHitDTO $hit): string => $hit->id, $hits));
    }
}
