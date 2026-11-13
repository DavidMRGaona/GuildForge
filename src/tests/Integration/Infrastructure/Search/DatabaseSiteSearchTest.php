<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Search;

use App\Application\Search\DTOs\SiteSearchHitDTO;
use App\Application\Search\DTOs\SiteSearchQueryDTO;
use App\Application\Search\DTOs\SiteSearchResultDTO;
use App\Application\Search\Services\SearchSourceRegistryInterface;
use App\Domain\Search\Enums\SearchAudience;
use App\Infrastructure\Search\Persistence\Eloquent\Models\SearchDocumentModel;
use App\Infrastructure\Search\Search\DatabaseSiteSearch;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;
use RuntimeException;
use stdClass;
use Tests\Support\Logging\RecordingLogger;
use Tests\Support\Search\CheckingSearchSource;
use Tests\Support\Search\SnippetWithholdingSearchSource;
use Tests\TestCase;

final class DatabaseSiteSearchTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const array TYPES = ['events', 'articles', 'galleries'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 11, 15)->setTime(12, 0));
        // No source of these types checks its results unless a test registers one
        $this->app->make(SearchSourceRegistryInterface::class)->clear();
    }

    public function test_finds_by_title_keywords_meta_and_body(): void
    {
        $this->document(['title' => 'Liga de DRAGONES']);
        $this->document(['title' => 'Por palabras clave', 'keywords' => 'dragones, fantasía']);
        $this->document(['title' => 'Por meta', 'meta' => 'Sala Dragones']);
        $this->document(['title' => 'Por cuerpo', 'body' => 'Una partida con dragones y mazmorras.']);
        $this->document(['title' => 'Sin relación', 'body' => 'Nada que ver.']);

        $result = $this->search('dragones');

        $this->assertEqualsCanonicalizing(
            ['Liga de DRAGONES', 'Por palabras clave', 'Por meta', 'Por cuerpo'],
            $this->titles($result),
        );
        $this->assertSame(4, $result->total);
    }

    public function test_every_term_must_appear_in_some_field(): void
    {
        $this->document(['title' => 'Torneo de Warhammer', 'body' => 'En la sala grande.']);
        $this->document(['title' => 'Torneo de rol', 'body' => 'Una tarde de rol.']);
        $this->document(['title' => 'Partida', 'keywords' => 'torneo', 'meta' => 'Warhammer 40K']);

        $this->assertEqualsCanonicalizing(
            ['Torneo de Warhammer', 'Partida'],
            $this->titles($this->search('torneo de warhammer')),
        );
    }

    public function test_title_matches_rank_first(): void
    {
        $this->document(['title' => 'Jornada abierta', 'body' => 'Habrá ajedrez.', 'published_at' => now()->subDay()]);
        $this->document(['title' => 'Club de ajedrez', 'published_at' => now()->subYear()]);
        $this->document(['title' => 'Ajedrez y Warhammer', 'published_at' => now()->subYears(2)]);

        $this->assertSame(
            ['Club de ajedrez', 'Ajedrez y Warhammer', 'Jornada abierta'],
            $this->titles($this->search('ajedrez')),
        );
    }

    public function test_more_title_terms_rank_first_then_upcoming(): void
    {
        $this->document(['title' => 'Liga Warhammer', 'keywords' => 'torneo', 'starts_at' => now()->subWeeks(2), 'ends_at' => now()->subWeeks(2)->addHours(4)]);
        $this->document(['title' => 'Torneo de rol', 'body' => 'Con mesa de Warhammer.', 'starts_at' => now()->addWeek()]);
        $this->document(['title' => 'Torneo de Warhammer', 'starts_at' => now()->subMonth(), 'ends_at' => now()->subMonth()->addHours(4)]);

        $this->assertSame(
            ['Torneo de Warhammer', 'Torneo de rol', 'Liga Warhammer'],
            $this->titles($this->search('torneo warhammer')),
        );
    }

    public function test_upcoming_rank_before_past(): void
    {
        $this->document(['title' => 'Partida terminada', 'body' => 'mazmorra', 'starts_at' => now()->addMonth(), 'finished' => true]);
        $this->document(['title' => 'Partida celebrada', 'body' => 'mazmorra', 'starts_at' => now()->subDays(3), 'ends_at' => now()->subDays(2)]);
        $this->document(['title' => 'Partida próxima', 'body' => 'mazmorra', 'starts_at' => now()->addDays(2)]);
        $this->document(['title' => 'Artículo', 'type' => 'articles', 'body' => 'mazmorra', 'published_at' => now()->subWeek()]);
        $this->document(['title' => 'Sin fecha', 'type' => 'galleries', 'body' => 'mazmorra']);

        $this->assertSame(
            ['Partida próxima', 'Artículo', 'Sin fecha', 'Partida terminada', 'Partida celebrada'],
            $this->titles($this->search('mazmorra')),
        );
    }

    public function test_past_flag_uses_end_then_start_then_finished(): void
    {
        $this->document(['title' => 'Empezada sin terminar', 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour()]);
        $this->document(['title' => 'Empezada sin fin', 'starts_at' => now()->subMinute()]);
        $this->document(['title' => 'Terminada', 'starts_at' => now()->subHours(3), 'ends_at' => now()->subMinute()]);
        $this->document(['title' => 'Futura pero cerrada', 'starts_at' => now()->addDay(), 'finished' => true]);
        $this->document(['title' => 'Futura', 'starts_at' => now()->addDay()]);
        $this->document(['title' => 'Sin fechas', 'type' => 'articles', 'published_at' => now()->subYear()]);
        $this->document(['title' => 'Sin fechas pero cerrada', 'finished' => true]);

        $past = [];
        foreach ($this->search('partida', limit: 10)->hits as $hit) {
            $past[$hit->title] = $hit->past;
        }
        ksort($past);

        $this->assertSame([
            'Empezada sin fin' => true,
            'Empezada sin terminar' => false,
            'Futura' => false,
            'Futura pero cerrada' => true,
            'Sin fechas' => false,
            'Sin fechas pero cerrada' => true,
            'Terminada' => true,
        ], $past);
    }

    public function test_filters_by_audience(): void
    {
        $this->document(['title' => 'Rol para todos', 'audience' => SearchAudience::Public->value]);
        $this->document(['title' => 'Rol con sesión', 'audience' => SearchAudience::Authenticated->value]);
        $this->document(['title' => 'Rol para socios', 'audience' => SearchAudience::Members->value]);

        $this->assertSame(['Rol para todos'], $this->titles($this->search('rol', [SearchAudience::Public])));
        $this->assertEqualsCanonicalizing(
            ['Rol para todos', 'Rol con sesión'],
            $this->titles($this->search('rol', [SearchAudience::Public, SearchAudience::Authenticated])),
        );
        $this->assertSame([], $this->search('rol', [])->hits);
    }

    public function test_ignores_types_not_requested(): void
    {
        $this->document(['title' => 'Mesa de Warhammer', 'type' => 'gametables.tables', 'module' => 'game-tables']);
        $this->document(['title' => 'Evento de Warhammer']);

        $result = $this->search('warhammer');

        $this->assertSame(['Evento de Warhammer'], $this->titles($result));
        $this->assertSame(['events' => 1], $result->counts);
        $this->assertSame(0, $this->search('warhammer', type: 'gametables.tables')->total);
    }

    public function test_like_wildcards_in_query_are_literal(): void
    {
        $this->document(['title' => 'Descuento del 50% para socios']);
        $this->document(['title' => 'Premio de 500 puntos']);
        $this->document(['title' => 'Mapa foo_bar']);
        $this->document(['title' => 'Mapa fooxbar']);
        $this->document(['title' => 'Ruta C:\\juegos']);
        $this->document(['title' => 'Ruta C:juegos']);

        $this->assertSame(['Descuento del 50% para socios'], $this->titles($this->search('50%')));
        $this->assertSame(['Mapa foo_bar'], $this->titles($this->search('foo_bar')));
        $this->assertSame(['Ruta C:\\juegos'], $this->titles($this->search('c:\\juegos')));
    }

    public function test_counts_per_type_and_offset_paging(): void
    {
        foreach (range(1, 3) as $day) {
            $this->document(['title' => "Evento {$day} de rol", 'starts_at' => now()->addDays($day)]);
        }
        foreach (range(1, 2) as $day) {
            $this->document(['title' => "Artículo {$day} de rol", 'type' => 'articles', 'published_at' => now()->subDays($day)]);
        }
        $this->document(['title' => 'Galería de miniaturas', 'type' => 'galleries']);

        $first = $this->search('rol', limit: 2);
        $this->assertSame(['events' => 3, 'articles' => 2], $first->counts);
        $this->assertSame(5, $first->total);
        $this->assertSame(['Evento 3 de rol', 'Evento 2 de rol'], $this->titles($first));

        $last = $this->search('rol', offset: 4, limit: 2);
        $this->assertSame(['Artículo 2 de rol'], $this->titles($last));
        $this->assertSame(5, $last->total);

        $articles = $this->search('rol', type: 'articles', limit: 20);
        $this->assertSame(['Artículo 1 de rol', 'Artículo 2 de rol'], $this->titles($articles));
        $this->assertSame(2, $articles->total);
        $this->assertSame(['events' => 3, 'articles' => 2], $articles->counts);
    }

    public function test_hit_carries_the_document_fields_and_a_snippet(): void
    {
        $startsAt = now()->addDays(2)->toImmutable();
        $this->document([
            'title' => 'Torneo de primavera',
            'body' => str_repeat('Texto de relleno sin interés. ', 10).'La final de Warhammer se juega en la sala grande.',
            'meta' => 'Sala grande',
            'url' => '/eventos/torneo-de-primavera',
            'image_public_id' => 'events/2026/11/torneo.jpg',
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addHours(4),
        ]);

        $hit = $this->search('warhammer')->hits[0];

        $this->assertSame('events', $hit->type);
        $this->assertSame('/eventos/torneo-de-primavera', $hit->url);
        $this->assertSame('Sala grande', $hit->meta);
        $this->assertSame('events/2026/11/torneo.jpg', $hit->imagePublicId);
        $this->assertNotNull($hit->startsAt);
        $this->assertSame($startsAt->getTimestamp(), $hit->startsAt->getTimestamp());
        $this->assertSame($startsAt->addHours(4)->getTimestamp(), $hit->endsAt?->getTimestamp());
        $this->assertFalse($hit->past);
        $this->assertContains(['text' => 'Warhammer', 'match' => true], $hit->snippet);
    }

    public function test_snippet_falls_back_to_meta_when_the_body_has_no_match(): void
    {
        $this->document(['title' => 'Torneo', 'body' => 'Una tarde de juegos.', 'meta' => 'Club Warhammer']);

        $this->assertContains(['text' => 'Warhammer', 'match' => true], $this->search('warhammer')->hits[0]->snippet);
    }

    public function test_rows_the_source_no_longer_shows_are_left_out_of_hits_and_counts(): void
    {
        $this->document(['title' => 'Dragones del evento']);
        $this->document(['type' => 'articles', 'source_id' => 'a-visible', 'title' => 'Dragones visibles']);
        $this->document(['type' => 'articles', 'source_id' => 'a-hidden', 'title' => 'Dragones ocultos']);
        $source = $this->checking(CheckingSearchSource::allowing('articles', ['a-visible']));
        $user = new stdClass;

        $result = $this->search('dragones', user: $user);

        $this->assertEqualsCanonicalizing(['Dragones del evento', 'Dragones visibles'], $this->titles($result));
        $this->assertSame(['events' => 1, 'articles' => 1], $result->counts);
        $this->assertSame(2, $result->total);
        // One check for the whole page, with the person searching
        $this->assertCount(1, $source->checks);
        $this->assertEqualsCanonicalizing(['a-visible', 'a-hidden'], $source->checks[0]['ids']);
        $this->assertSame($user, $source->checks[0]['user']);
    }

    public function test_a_type_left_without_rows_is_removed_from_the_counts(): void
    {
        $this->document(['title' => 'Dragones del evento']);
        $this->document(['type' => 'articles', 'source_id' => 'a-hidden', 'title' => 'Dragones ocultos']);
        $this->checking(CheckingSearchSource::allowing('articles', []));

        $tab = $this->search('dragones', type: 'articles');

        $this->assertSame([], $tab->hits);
        $this->assertSame(['events' => 1], $tab->counts);
        $this->assertSame(0, $tab->total);
    }

    public function test_a_check_that_fails_leaves_out_its_type_and_logs_an_error(): void
    {
        $logger = new RecordingLogger;
        $this->app->instance(LoggerInterface::class, $logger);
        $this->document(['title' => 'Dragones del evento']);
        $this->document(['type' => 'articles', 'source_id' => 'a1', 'title' => 'Dragones uno']);
        $this->document(['type' => 'articles', 'source_id' => 'a2', 'title' => 'Dragones dos']);
        $this->checking(new CheckingSearchSource('articles', static fn (): never => throw new RuntimeException('Gone')));

        $result = $this->search('dragones');

        $this->assertSame(['Dragones del evento'], $this->titles($result));
        $this->assertSame(['events' => 1], $result->counts);
        $this->assertSame(1, $result->total);
        $this->assertSame(['error'], array_column($logger->records, 'level'));
    }

    public function test_a_row_whose_stored_text_is_stale_shows_its_title_without_the_snippet(): void
    {
        $this->document(['type' => 'articles', 'source_id' => 'a-stale', 'title' => 'Tema de dragones', 'body' => 'Texto oculto por la moderación sobre dragones.']);
        $this->document(['type' => 'articles', 'source_id' => 'a-fresh', 'title' => 'Otro tema', 'body' => 'Un texto que sigue valiendo sobre dragones.']);
        $source = $this->withholding(SnippetWithholdingSearchSource::withholding('articles', ['a-stale']));
        $user = new stdClass;

        $result = $this->search('dragones', user: $user);

        $hits = [];
        foreach ($result->hits as $hit) {
            $hits[$hit->title] = $hit;
        }
        $this->assertSame([], $hits['Tema de dragones']->snippet);
        $this->assertContains(['text' => 'dragones', 'match' => true], $hits['Otro tema']->snippet);
        // The result stays: counts and total are untouched
        $this->assertSame(['articles' => 2], $result->counts);
        $this->assertSame(2, $result->total);
        $this->assertCount(1, $source->calls);
        $this->assertEqualsCanonicalizing(['a-stale', 'a-fresh'], $source->calls[0]['ids']);
        $this->assertSame($user, $source->calls[0]['user']);
    }

    public function test_a_failing_snippet_check_shows_its_type_without_snippets_and_logs_an_error(): void
    {
        $logger = new RecordingLogger;
        $this->app->instance(LoggerInterface::class, $logger);
        $this->document(['title' => 'Dragones del evento', 'body' => 'Una tarde con dragones.']);
        $this->document(['type' => 'articles', 'source_id' => 'a1', 'title' => 'Dragones uno', 'body' => 'Más dragones.']);
        $this->withholding(new SnippetWithholdingSearchSource('articles', static fn (): never => throw new RuntimeException('Gone')));

        $result = $this->search('dragones');

        $snippets = [];
        foreach ($result->hits as $hit) {
            $snippets[$hit->title] = $hit->snippet;
        }
        $this->assertSame([], $snippets['Dragones uno']);
        $this->assertNotSame([], $snippets['Dragones del evento']);
        $this->assertSame(2, $result->total);
        $this->assertSame(['error'], array_column($logger->records, 'level'));
    }

    public function test_only_the_rows_of_the_page_are_checked_and_paging_stays_coherent(): void
    {
        foreach (['a1', 'a2', 'a3', 'a4'] as $position => $id) {
            $this->document(['type' => 'articles', 'source_id' => $id, 'title' => "Dragones {$id}", 'published_at' => now()->subDays($position)]);
        }
        $source = $this->checking(CheckingSearchSource::allowing('articles', ['a1', 'a3', 'a4']));

        $first = $this->search('dragones', offset: 0, limit: 2);
        $second = $this->search('dragones', offset: 2, limit: 2);

        $this->assertSame(['Dragones a1'], $this->titles($first));
        $this->assertSame(3, $first->total);
        $this->assertSame(['Dragones a3', 'Dragones a4'], $this->titles($second));
        $this->assertSame(4, $second->total);
        // "More" is still offered after the shorter first page, and nothing is shown twice
        $this->assertTrue(count($first->hits) < $first->total);
        $this->assertSame([['a1', 'a2'], ['a3', 'a4']], array_column($source->checks, 'ids'));
    }

    public function test_query_without_terms_finds_nothing(): void
    {
        $this->document(['title' => 'Rol']);

        $result = $this->search(' ¿? ');

        $this->assertSame([], $result->hits);
        $this->assertSame([], $result->counts);
        $this->assertSame(0, $result->total);
    }

    /**
     * @param  list<SearchAudience>  $audiences
     */
    private function search(
        string $query,
        array $audiences = [SearchAudience::Public],
        ?string $type = null,
        int $offset = 0,
        int $limit = 20,
        ?object $user = null,
    ): SiteSearchResultDTO {
        return $this->app->make(DatabaseSiteSearch::class)->search(new SiteSearchQueryDTO(
            query: $query,
            audiences: $audiences,
            types: self::TYPES,
            type: $type,
            offset: $offset,
            limit: $limit,
            user: $user,
            includeHelp: false,
        ));
    }

    private function withholding(SnippetWithholdingSearchSource $source): SnippetWithholdingSearchSource
    {
        $this->app->instance(SnippetWithholdingSearchSource::class, $source);
        $this->app->make(SearchSourceRegistryInterface::class)->register(SnippetWithholdingSearchSource::class);

        return $source;
    }

    private function checking(CheckingSearchSource $source): CheckingSearchSource
    {
        $this->app->instance(CheckingSearchSource::class, $source);
        $this->app->make(SearchSourceRegistryInterface::class)->register(CheckingSearchSource::class);

        return $source;
    }

    /**
     * @return list<string>
     */
    private function titles(SiteSearchResultDTO $result): array
    {
        return array_map(static fn (SiteSearchHitDTO $hit): string => $hit->title, $result->hits);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function document(array $attributes): void
    {
        SearchDocumentModel::query()->create([
            'type' => 'events',
            'source_id' => (string) Str::uuid(),
            'body' => '',
            'keywords' => 'partida',
            'url' => '/'.Str::uuid(),
            'audience' => SearchAudience::Public->value,
            'locale' => 'es',
            ...$attributes,
        ]);
    }
}
