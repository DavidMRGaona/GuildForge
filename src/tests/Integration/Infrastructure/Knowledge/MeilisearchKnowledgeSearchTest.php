<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Knowledge;

use App\Application\Knowledge\DTOs\Response\KnowledgeSearchHitDTO;
use App\Domain\Knowledge\Entities\Guide;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeGuideModel;
use App\Infrastructure\Knowledge\Search\MeilisearchKnowledgeSearch;
use App\Infrastructure\Knowledge\Search\ScoutKnowledgeIndexer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Meilisearch\Client;
use Meilisearch\Contracts\TasksQuery;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\Support\Knowledge\KnowledgeGuides;
use Tests\TestCase;

/**
 * Runs against a real Meilisearch: MEILISEARCH_HOST (and MEILISEARCH_KEY) must point at it.
 */
#[Group('meilisearch')]
final class MeilisearchKnowledgeSearchTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $host = $this->environment('MEILISEARCH_HOST');
        if ($host === '') {
            $this->markTestSkipped('MEILISEARCH_HOST is not set.');
        }

        $prefix = $this->environment('SCOUT_PREFIX');
        $key = $this->environment('MEILISEARCH_KEY');
        config([
            'scout.driver' => 'meilisearch',
            'scout.prefix' => ($prefix !== '' ? $prefix : 'kbtest_').'t'.getmypid().'_',
            'scout.meilisearch.host' => $host,
            'scout.meilisearch.key' => $key !== '' ? $key : null,
        ]);
    }

    private function environment(string $name): string
    {
        $value = getenv($name);

        return is_string($value) ? $value : '';
    }

    protected function tearDown(): void
    {
        if (config('scout.driver') === 'meilisearch') {
            $client = $this->app->make(Client::class);
            $client->waitForTask($client->deleteIndex((new KnowledgeGuideModel)->searchableAs())['taskUid']);
        }

        parent::tearDown();
    }

    private function store(string $guideId, string $title, string $body, string $locale = 'es', bool $active = true): void
    {
        KnowledgeGuideModel::query()->create([
            'guide_id' => $guideId,
            'locale' => $locale,
            'source' => 'core',
            'module' => null,
            'audience' => 'site',
            'category_id' => 'getting-started',
            'sort_order' => 10,
            'title' => $title,
            'summary' => 'Resumen',
            'keywords' => ['ayuda'],
            'body_format' => 'markdown',
            'body' => $body,
            'body_text' => $body,
            'conditions' => [],
            'related' => [],
            'featured' => false,
            'content_hash' => str_repeat('a', 40),
            'active' => $active,
        ]);
    }

    private function reindex(): void
    {
        $result = $this->app->make(ScoutKnowledgeIndexer::class)->reindex();
        $this->assertNull($result->error);
        $this->waitForTasks();
    }

    private function waitForTasks(): void
    {
        $client = $this->app->make(Client::class);
        $deadline = microtime(true) + 20;
        while (microtime(true) < $deadline) {
            $pending = $client->getTasks((new TasksQuery)->setStatuses(['enqueued', 'processing']));
            if ($pending->getResults() === []) {
                return;
            }
            usleep(100_000);
        }

        throw new RuntimeException('Meilisearch tasks did not finish in time.');
    }

    /**
     * @param  list<Guide>  $candidates
     * @return list<string>
     */
    private function search(string $query, array $candidates): array
    {
        return array_map(
            static fn (KnowledgeSearchHitDTO $hit): string => $hit->id,
            $this->app->make(MeilisearchKnowledgeSearch::class)->search($query, $candidates, 10),
        );
    }

    public function test_hits_outside_candidates_are_discarded(): void
    {
        $this->store('core.visible', 'Inscripción en partidas', 'Cómo hacer la inscripción.');
        $this->store('core.secret', 'Inscripción secreta', 'Otra inscripción.');
        $this->reindex();

        $this->assertSame(['core.visible'], $this->search('inscripción', [KnowledgeGuides::make('core.visible', title: 'Inscripción en partidas')]));
        $this->assertSame([], $this->search('inscripción', []));
    }

    public function test_typos_still_match(): void
    {
        $this->store('core.signup', 'Inscripción en partidas', 'Cómo hacer la inscripción en una mesa.');
        $this->reindex();

        $this->assertSame(['core.signup'], $this->search('inscripcon', [KnowledgeGuides::make('core.signup')]));
        $this->assertSame([], $this->search('dragones', [KnowledgeGuides::make('core.signup')]));
    }

    public function test_synonyms_and_snippets(): void
    {
        $this->store('core.signup', 'Apuntarse', 'Para apuntarte a una mesa pulsa el botón <Apuntarme>.');
        $this->reindex();

        $hits = $this->app->make(MeilisearchKnowledgeSearch::class)->search('partida', [KnowledgeGuides::make('core.signup')], 10);

        $this->assertSame(['core.signup'], array_map(static fn (KnowledgeSearchHitDTO $hit): string => $hit->id, $hits));
        $this->assertContains(['text' => 'mesa', 'match' => true], $hits[0]->snippet);
        $this->assertStringContainsString('<Apuntarme>', implode('', array_column($hits[0]->snippet, 'text')));
    }

    public function test_inactive_guides_and_other_locales_are_not_found(): void
    {
        $this->store('core.old', 'Inscripción antigua', 'Ya no vale.', active: false);
        $this->store('core.english', 'Inscripción english', 'Signup.', locale: 'en');
        $this->reindex();

        $this->assertSame([], $this->search('inscripción', [KnowledgeGuides::make('core.old'), KnowledgeGuides::make('core.english')]));
    }
}
