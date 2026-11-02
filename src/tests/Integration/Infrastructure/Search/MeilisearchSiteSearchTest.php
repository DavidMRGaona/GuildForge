<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Search;

use App\Application\Knowledge\Services\GuideVisibilityServiceInterface;
use App\Application\Search\DTOs\SiteSearchHitDTO;
use App\Application\Search\DTOs\SiteSearchQueryDTO;
use App\Application\Search\DTOs\SiteSearchResultDTO;
use App\Domain\Knowledge\Entities\Guide;
use App\Domain\Knowledge\Entities\KnowledgeCategory;
use App\Domain\Knowledge\Enums\GuideAudience;
use App\Domain\Knowledge\ValueObjects\GuideId;
use App\Domain\Search\Enums\SearchAudience;
use App\Infrastructure\Knowledge\Search\MeilisearchHitMapper;
use App\Infrastructure\Search\Persistence\Eloquent\Models\SearchDocumentModel;
use App\Infrastructure\Search\Search\MeilisearchFilter;
use App\Infrastructure\Search\Search\MeilisearchSiteSearch;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Meilisearch\Client;
use Meilisearch\Contracts\MultiSearchFederation;
use Meilisearch\Contracts\SearchQuery;
use Mockery;
use stdClass;
use Tests\Support\Knowledge\KnowledgeGuides;
use Tests\TestCase;

final class MeilisearchSiteSearchTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const array TYPES = ['events', 'articles', 'galleries'];

    private const string DOCUMENTS = 'site_search_documents';

    private const string GUIDES = 'site_knowledge_guides';

    private const string PRE = MeilisearchHitMapper::HIGHLIGHT_PRE_TAG;

    private const string POST = MeilisearchHitMapper::HIGHLIGHT_POST_TAG;

    /** @var list<array{queries: list<array<string, mixed>>, federation: array<string, mixed>|null}> */
    private array $calls = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['scout.prefix' => 'site_']);
        $this->travelTo(now()->setDate(2026, 11, 15)->setTime(12, 0));
        $this->visibleGuides([
            KnowledgeGuides::make('core.signup', title: 'Apuntarse a una partida'),
            KnowledgeGuides::make('core.password', title: 'Cambiar la contraseña'),
        ]);
    }

    public function test_builds_three_weighted_queries(): void
    {
        $this->engineAnswers(['hits' => [], 'facetsByIndex' => []]);
        $now = now()->getTimestamp();

        $this->search('dragones', offset: 20, limit: 20);

        $this->assertCount(1, $this->calls);
        $call = $this->calls[0];
        $this->assertSame(['limit' => 20, 'offset' => 20, 'facetsByIndex' => [self::DOCUMENTS => ['type'], self::GUIDES => ['locale']]], $call['federation']);

        $queries = $call['queries'];
        $this->assertSame([self::DOCUMENTS, self::DOCUMENTS, self::GUIDES], array_column($queries, 'indexUid'));
        $this->assertSame(['dragones', 'dragones', 'dragones'], array_column($queries, 'q'));
        $this->assertSame([['weight' => 1.0], ['weight' => 0.5], ['weight' => 1.0]], array_column($queries, 'federationOptions'));

        $documents = 'type IN ["events", "articles", "galleries"] AND audience IN ["public", "authenticated"]';
        $this->assertSame(["{$documents} AND finished = false AND (ends_at_ts >= {$now} OR ends_at_ts IS NULL)"], $queries[0]['filter']);
        $this->assertSame(["{$documents} AND (finished = true OR ends_at_ts < {$now})"], $queries[1]['filter']);
        $this->assertSame(['active = true AND locale = "es" AND audience = "site" AND guide_id IN ["core.signup", "core.password"]'], $queries[2]['filter']);

        foreach ($queries as $query) {
            $this->assertArrayNotHasKey('limit', $query);
            $this->assertArrayNotHasKey('offset', $query);
            $this->assertSame(self::PRE, $query['highlightPreTag']);
            $this->assertSame(self::POST, $query['highlightPostTag']);
        }
        $this->assertSame(['id'], $queries[0]['attributesToRetrieve']);
        $this->assertSame(['guide_id', 'locale'], $queries[2]['attributesToRetrieve']);
    }

    public function test_help_is_left_out_without_a_user_or_without_visible_guides(): void
    {
        $this->engineAnswers(['hits' => [], 'facetsByIndex' => []]);

        $this->search('dragones', user: null);
        $this->search('dragones', includeHelp: false);
        $this->visibleGuides([]);
        $this->search('dragones');

        $this->assertCount(3, $this->calls);
        foreach ($this->calls as $call) {
            $this->assertSame([self::DOCUMENTS, self::DOCUMENTS], array_column($call['queries'], 'indexUid'));
            $this->assertSame([self::DOCUMENTS => ['type']], $call['federation']['facetsByIndex'] ?? null);
        }
    }

    public function test_quotes_hostile_values_in_filters(): void
    {
        $hostile = 'evil" OR audience = "members\\';
        $this->assertSame('"evil\\" OR audience = \\"members\\\\"', MeilisearchFilter::quote($hostile));
        $this->assertSame('type IN ["a\\"b"]', MeilisearchFilter::in('type', ['a"b']));

        $this->engineAnswers(['hits' => [], 'facetsByIndex' => []]);

        $this->search('dragones', types: ['events', $hostile], user: null);

        $filter = $this->calls[0]['queries'][0]['filter'][0] ?? '';
        $this->assertIsString($filter);
        $this->assertStringStartsWith('type IN ["events", "evil\\" OR audience = \\"members\\\\"] AND audience IN ["public", "authenticated"] AND ', $filter);
    }

    public function test_tab_sends_only_its_query(): void
    {
        $this->engineAnswers(
            federated: ['hits' => []],
            counts: ['results' => [
                ['indexUid' => self::DOCUMENTS, 'hits' => [], 'facetDistribution' => ['type' => ['events' => 4]]],
                ['indexUid' => self::GUIDES, 'hits' => [], 'facetDistribution' => ['locale' => ['es' => 1]]],
            ]],
        );

        $this->search('dragones', type: 'help', offset: 0, limit: 20);
        $this->search('dragones', type: 'events', offset: 0, limit: 20);

        $federated = array_values(array_filter($this->calls, static fn (array $call): bool => $call['federation'] !== null));
        $this->assertCount(2, $federated);

        $this->assertSame([self::GUIDES], array_column($federated[0]['queries'], 'indexUid'));
        $this->assertSame(['limit' => 20, 'offset' => 0], $federated[0]['federation']);

        $this->assertSame([self::DOCUMENTS, self::DOCUMENTS], array_column($federated[1]['queries'], 'indexUid'));
        foreach ($federated[1]['queries'] as $query) {
            $filter = $query['filter'][0] ?? '';
            $this->assertIsString($filter);
            $this->assertStringStartsWith('type IN ["events"] AND ', $filter);
        }

        // The counts of every tab still come along, in one request without federation
        $counts = array_values(array_filter($this->calls, static fn (array $call): bool => $call['federation'] === null));
        $this->assertCount(2, $counts);
        $this->assertSame([self::DOCUMENTS, self::GUIDES], array_column($counts[0]['queries'], 'indexUid'));
        $this->assertSame([0, 0], array_column($counts[0]['queries'], 'limit'));
        $this->assertSame([['type'], ['locale']], array_column($counts[0]['queries'], 'facets'));
        $this->assertSame(['type IN ["events", "articles", "galleries"] AND audience IN ["public", "authenticated"]'], $counts[0]['queries'][0]['filter']);
    }

    public function test_maps_hits_in_engine_order_and_drops_unknown_ids(): void
    {
        $upcoming = $this->document([
            'title' => 'Partida de dragones',
            'url' => '/eventos/partida',
            'meta' => 'Sala 1',
            'image_public_id' => 'events/dragones.jpg',
            'starts_at' => now()->addDays(2),
        ]);
        $past = $this->document(['title' => 'Liga de dragones', 'starts_at' => now()->subDays(3), 'ends_at' => now()->subDays(2)]);
        $members = $this->document(['title' => 'Solo socios', 'audience' => SearchAudience::Members->value]);

        $this->engineAnswers([
            'hits' => [
                $this->documentHit($past, ['body' => 'Una liga de '.self::PRE.'dragones'.self::POST, 'meta' => null]),
                $this->guideHit('core.signup', ['body_text' => 'Cómo '.self::PRE.'apuntarse'.self::POST.' a una mesa', 'summary' => 'Resumen']),
                $this->documentHit((string) Str::uuid(), []),
                $this->documentHit('not-a-uuid', []),
                $this->documentHit($members, []),
                $this->guideHit('core.hidden', []),
                $this->documentHit($upcoming, ['body' => 'Sin coincidencia', 'meta' => 'Sala de '.self::PRE.'dragones'.self::POST]),
            ],
            'facetsByIndex' => [
                self::DOCUMENTS => ['distribution' => ['type' => ['events' => 3]]],
                self::GUIDES => ['distribution' => ['locale' => ['es' => 1]]],
            ],
        ]);

        $result = $this->search('dragones');

        $this->assertSame(
            ['Liga de dragones', 'Apuntarse a una partida', 'Partida de dragones'],
            array_map(static fn (SiteSearchHitDTO $hit): string => $hit->title, $result->hits),
        );

        [$pastHit, $help, $upcomingHit] = $result->hits;
        $this->assertSame('events', $pastHit->type);
        $this->assertTrue($pastHit->past);
        $this->assertSame([['text' => 'Una liga de ', 'match' => false], ['text' => 'dragones', 'match' => true]], $pastHit->snippet);

        $this->assertSame('help', $help->type);
        $this->assertSame(route('knowledge.show', 'core.signup'), $help->url);
        $this->assertSame('Primeros pasos', $help->meta);
        $this->assertFalse($help->past);
        $this->assertNull($help->imagePublicId);
        $this->assertNull($help->startsAt);
        $this->assertSame([
            ['text' => 'Cómo ', 'match' => false],
            ['text' => 'apuntarse', 'match' => true],
            ['text' => ' a una mesa', 'match' => false],
        ], $help->snippet);

        $this->assertFalse($upcomingHit->past);
        $this->assertSame('/eventos/partida', $upcomingHit->url);
        $this->assertSame('Sala 1', $upcomingHit->meta);
        $this->assertSame('events/dragones.jpg', $upcomingHit->imagePublicId);
        $this->assertSame(now()->addDays(2)->getTimestamp(), $upcomingHit->startsAt?->getTimestamp());
        $this->assertSame([['text' => 'Sala de ', 'match' => false], ['text' => 'dragones', 'match' => true]], $upcomingHit->snippet);
    }

    public function test_counts_come_from_type_facet_plus_help_hits(): void
    {
        $this->engineAnswers(
            federated: [
                'hits' => [],
                'facetsByIndex' => [
                    self::DOCUMENTS => ['distribution' => ['type' => ['galleries' => 2, 'events' => 3, 'articles' => 0, 'unknown' => 9]]],
                    self::GUIDES => ['distribution' => ['locale' => ['es' => 2]]],
                ],
            ],
            counts: ['results' => [
                ['indexUid' => self::DOCUMENTS, 'hits' => [], 'facetDistribution' => ['type' => ['galleries' => 2, 'events' => 3]]],
                ['indexUid' => self::GUIDES, 'hits' => [], 'facetDistribution' => ['locale' => ['es' => 2]]],
            ]],
        );

        $all = $this->search('dragones');
        $this->assertSame(['events' => 3, 'galleries' => 2, 'help' => 2], $all->counts);
        $this->assertSame(7, $all->total);

        $tab = $this->search('dragones', type: 'galleries');
        $this->assertSame(['events' => 3, 'galleries' => 2, 'help' => 2], $tab->counts);
        $this->assertSame(2, $tab->total);

        // A tab without matches answers from the counts alone
        $before = count($this->calls);
        $empty = $this->search('dragones', type: 'articles');
        $this->assertSame(0, $empty->total);
        $this->assertSame([], $empty->hits);
        $this->assertSame([null], array_column(array_slice($this->calls, $before), 'federation'));
    }

    public function test_blank_query_asks_nothing(): void
    {
        $this->engineAnswers(['hits' => []]);

        $result = $this->search('   ');

        $this->assertEquals(SiteSearchResultDTO::empty(), $result);
        $this->assertSame([], $this->calls);
    }

    /**
     * @param  list<string>  $types
     */
    private function search(
        string $query,
        array $types = self::TYPES,
        ?string $type = null,
        int $offset = 0,
        int $limit = 20,
        ?object $user = new stdClass,
        bool $includeHelp = true,
    ): SiteSearchResultDTO {
        return $this->app->make(MeilisearchSiteSearch::class)->search(new SiteSearchQueryDTO(
            query: $query,
            audiences: [SearchAudience::Public, SearchAudience::Authenticated],
            types: $types,
            type: $type,
            offset: $offset,
            limit: $limit,
            user: $user,
            includeHelp: $includeHelp,
        ));
    }

    /**
     * Records every multi-search; federated requests get $federated, the others $counts.
     *
     * @param  array<string, mixed>  $federated
     * @param  array<string, mixed>  $counts
     */
    private function engineAnswers(array $federated, array $counts = ['results' => []]): void
    {
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('multiSearch')->andReturnUsing(
            function (array $queries, ?MultiSearchFederation $federation = null) use ($federated, $counts): array {
                $this->calls[] = [
                    'queries' => array_values(array_map(static fn (SearchQuery $query): array => $query->toArray(), $queries)),
                    'federation' => $federation?->toArray(),
                ];

                return $federation instanceof MultiSearchFederation ? $federated : $counts;
            },
        );

        $this->app->instance(Client::class, $client);
    }

    /**
     * @param  list<Guide>  $guides
     */
    private function visibleGuides(array $guides): void
    {
        $this->app->instance(GuideVisibilityServiceInterface::class, new readonly class($guides) implements GuideVisibilityServiceInterface
        {
            /**
             * @param  list<Guide>  $guides
             */
            public function __construct(private array $guides) {}

            public function visibleGuides(object $user, GuideAudience $audience): array
            {
                return $this->guides;
            }

            public function findVisible(object $user, GuideId $id, GuideAudience $audience): ?Guide
            {
                return null;
            }

            public function visibleCategories(object $user, GuideAudience $audience): array
            {
                return [[
                    'category' => new KnowledgeCategory('getting-started', null, GuideAudience::Site, 10, 'heroicon-o-sparkles', 'knowledge.categories.getting-started'),
                    'guideCount' => count($this->guides),
                ]];
            }
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function document(array $attributes): string
    {
        return SearchDocumentModel::query()->create([
            'type' => 'events',
            'source_id' => (string) Str::uuid(),
            'body' => '',
            'url' => '/'.Str::uuid(),
            'audience' => SearchAudience::Public->value,
            'locale' => 'es',
            ...$attributes,
        ])->id;
    }

    /**
     * @param  array<string, mixed>  $formatted
     * @return array<string, mixed>
     */
    private function documentHit(string $id, array $formatted): array
    {
        return ['id' => $id, '_formatted' => $formatted, '_federation' => ['indexUid' => self::DOCUMENTS, 'queriesPosition' => 0]];
    }

    /**
     * @param  array<string, mixed>  $formatted
     * @return array<string, mixed>
     */
    private function guideHit(string $guideId, array $formatted): array
    {
        return ['guide_id' => $guideId, 'locale' => 'es', '_formatted' => $formatted, '_federation' => ['indexUid' => self::GUIDES, 'queriesPosition' => 2]];
    }
}
