<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Knowledge;

use App\Application\Knowledge\DTOs\Response\KnowledgeSearchHitDTO;
use App\Domain\Knowledge\Entities\Guide;
use App\Infrastructure\Knowledge\Search\DatabaseKnowledgeSearch;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Knowledge\KnowledgeGuides;
use Tests\TestCase;

final class DatabaseKnowledgeSearchTest extends TestCase
{
    private DatabaseKnowledgeSearch $search;

    /** @var list<Guide> */
    private array $candidates;

    protected function setUp(): void
    {
        parent::setUp();

        $this->search = $this->app->make(DatabaseKnowledgeSearch::class);
        $this->candidates = [
            KnowledgeGuides::make('core.signup', title: 'Inscripción en una partida', summary: 'Cómo apuntarte.', bodyText: 'Pulsa el botón para la inscripción <de> la mesa.'),
            KnowledgeGuides::make('core.password', title: 'Cambiar la contraseña', summary: 'Desde tu perfil.', bodyText: 'Abre el perfil y elige una clave nueva.'),
        ];
    }

    /**
     * @param  list<KnowledgeSearchHitDTO>  $hits
     * @return list<string>
     */
    private function ids(array $hits): array
    {
        return array_map(static fn (KnowledgeSearchHitDTO $hit): string => $hit->id, $hits);
    }

    public function test_accentless_query_finds_accented_titles(): void
    {
        $hits = $this->search->search('inscripcion', $this->candidates, 10);

        $this->assertSame(['core.signup'], $this->ids($hits));
        $this->assertSame('Inscripción en una partida', $hits[0]->title);
        $this->assertSame('getting-started', $hits[0]->categoryId);
    }

    public function test_accented_query_finds_accentless_text(): void
    {
        $this->assertSame(['core.password'], $this->ids($this->search->search('PÉRFIL', $this->candidates, 10)));
    }

    public function test_all_terms_must_match(): void
    {
        $this->assertSame(['core.signup'], $this->ids($this->search->search('inscripción mesa', $this->candidates, 10)));
        $this->assertSame([], $this->ids($this->search->search('inscripción contraseña', $this->candidates, 10)));
    }

    public function test_keywords_are_searched(): void
    {
        $candidates = [KnowledgeGuides::make('core.fees', title: 'Pagos', keywords: ['cuota anual'])];

        $this->assertSame(['core.fees'], $this->ids($this->search->search('cuota', $candidates, 10)));
    }

    public function test_title_matches_rank_above_body_matches(): void
    {
        $candidates = [
            KnowledgeGuides::make('core.body', title: 'Otra cosa', bodyText: 'Aquí se habla del calendario.', order: 1),
            KnowledgeGuides::make('core.summary', title: 'Algo más', summary: 'Sobre el calendario.', order: 2),
            KnowledgeGuides::make('core.keywords', title: 'Fechas', keywords: ['calendario'], order: 3),
            KnowledgeGuides::make('core.title', title: 'El calendario', order: 4),
        ];

        $this->assertSame(
            ['core.title', 'core.keywords', 'core.summary', 'core.body'],
            $this->ids($this->search->search('calendario', $candidates, 10)),
        );
    }

    public function test_ties_are_ordered_by_order_then_title(): void
    {
        $candidates = [
            KnowledgeGuides::make('core.c', title: 'Mesa c', order: 20),
            KnowledgeGuides::make('core.b', title: 'Mesa b', order: 10),
            KnowledgeGuides::make('core.a', title: 'Mesa a', order: 20),
        ];

        $this->assertSame(['core.b', 'core.a', 'core.c'], $this->ids($this->search->search('mesa', $candidates, 10)));
    }

    public function test_the_limit_is_applied(): void
    {
        $candidates = [
            KnowledgeGuides::make('core.a', title: 'Mesa a'),
            KnowledgeGuides::make('core.b', title: 'Mesa b'),
        ];

        $this->assertCount(1, $this->search->search('mesa', $candidates, 1));
        $this->assertSame([], $this->search->search('mesa', $candidates, 0));
    }

    public function test_only_candidates_are_returned(): void
    {
        $this->assertSame([], $this->search->search('inscripción', [], 10));
        $this->assertSame([], $this->ids($this->search->search('inscripción', [$this->candidates[1]], 10)));
    }

    public function test_terms_shorter_than_two_characters_are_ignored(): void
    {
        $this->assertSame([], $this->search->search('a', $this->candidates, 10));
        $this->assertSame(['core.password'], $this->ids($this->search->search('a perfil', $this->candidates, 10)));
    }

    public function test_stop_words_are_ignored_when_other_terms_remain(): void
    {
        $this->assertSame(['core.password'], $this->ids($this->search->search('como cambiar mi contraseña', $this->candidates, 10)));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function oddQueries(): iterable
    {
        foreach (['%', '_', '"', '.*', '(', str_repeat('a', 100), '   ', 'ÑANDÚ', '', '<script>', '\\', '/(?:)/'] as $query) {
            yield 'query '.json_encode($query) => [$query];
        }
    }

    #[DataProvider('oddQueries')]
    public function test_odd_queries_never_throw(string $q): void
    {
        $this->assertLessThanOrEqual(count($this->candidates), count($this->search->search($q, $this->candidates, 10)));
    }

    public function test_snippet_marks_matches_in_original_text(): void
    {
        $hits = $this->search->search('inscripcion', $this->candidates, 10);

        $matches = array_values(array_filter($hits[0]->snippet, static fn (array $segment): bool => $segment['match']));
        $this->assertNotEmpty($matches);
        $this->assertSame('inscripción', $matches[0]['text']);
        $this->assertStringContainsString('<de>', implode('', array_column($hits[0]->snippet, 'text')));
    }

    public function test_snippet_comes_from_the_summary_when_the_body_does_not_match(): void
    {
        $hits = $this->search->search('apuntarte', $this->candidates, 10);

        $this->assertSame('Cómo apuntarte.', implode('', array_column($hits[0]->snippet, 'text')));
    }
}
